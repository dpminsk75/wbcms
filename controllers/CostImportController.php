<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UploadedFile;
use yii\db\Query;
use PhpOffice\PhpSpreadsheet\IOFactory;
use app\components\getDPWidget;

/**
 * Веб-загрузка себестоимости из Excel через AJAX + просмотр/редактирование wbcards_costs.
 *
 * Маршруты:
 *   GET  /cost-import                    — страница с формой загрузки
 *   POST /cost-import/preview            — загрузка файла, парсинг, возврат JSON (без записи в БД)
 *   POST /cost-import/save               — сохранение подтверждённых данных в wbcards_costs
 *   GET  /cost-import/list               — просмотр записей с фильтром по датам/карточке
 *   POST /cost-import/update-price       — редактирование одной цены (AJAX)
 *
 * Требуется: composer require phpoffice/phpspreadsheet
 */
class CostImportController extends Controller
{
    // Разрешённые варианты названий колонки с nmID (в нижнем регистре, без пробелов по краям)
    private const NM_ID_HEADERS = ['nmid', 'артикул', 'артикул wb'];

    // Разрешённые варианты названий колонки с ценой
    private const PRICE_HEADERS = ['цена', 'себестоимость'];

    public function actionIndex()
    {
        return $this->render('index');
    }

    /**
     * Принимает файл, парсит его, возвращает список распознанных позиций и ошибок.
     * Ничего не пишет в БД — только предпросмотр.
     */
    public function actionPreview()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $file = UploadedFile::getInstanceByName('file');
        if ($file === null) {
            return ['success' => false, 'message' => 'Файл не выбран'];
        }

        $allowedExt = ['xlsx', 'xls'];
        if (!in_array(strtolower($file->extension), $allowedExt, true)) {
            return ['success' => false, 'message' => 'Поддерживаются только файлы .xlsx / .xls'];
        }

        $tmpPath = Yii::getAlias('@runtime/cost_import_' . uniqid() . '.' . $file->extension);
        if (!$file->saveAs($tmpPath)) {
            return ['success' => false, 'message' => 'Не удалось сохранить загруженный файл'];
        }

        try {
            $result = $this->parseFile($tmpPath);
        } catch (\Throwable $e) {
            @unlink($tmpPath);
            return ['success' => false, 'message' => 'Ошибка чтения файла: ' . $e->getMessage()];
        }

        @unlink($tmpPath);

        if ($result['nmIdCol'] === null || $result['priceCol'] === null) {
            return [
                'success' => false,
                'message' => 'Не удалось найти в заголовках нужные колонки. '
                    . 'Колонка с артикулом должна называться "nmID" / "Артикул" / "Артикул WB", '
                    . 'колонка с ценой — "Цена" / "Себестоимость".',
            ];
        }

        return [
            'success' => true,
            'items'   => $result['items'],   // [{row, nmID, price, name}]
            'errors'  => $result['errors'],  // [{row, reason, raw}]
        ];
    }

    /**
     * Сохраняет подтверждённые пользователем позиции в wbcards_costs.
     * Ожидает POST: date (Y-m-d), items (JSON-строка [{nmID, price}, ...])
     */
    public function actionSave()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        $date = $request->post('date');
        $itemsRaw = $request->post('items');

        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return ['success' => false, 'message' => 'Некорректная дата загрузки'];
        }

        $items = json_decode($itemsRaw, true);
        if (!is_array($items) || empty($items)) {
            return ['success' => false, 'message' => 'Нет данных для сохранения'];
        }

        $transaction = Yii::$app->db->beginTransaction();
        $processed = 0;

        try {
            foreach ($items as $item) {
                if (!isset($item['nmID'], $item['price'])) {
                    continue;
                }

                $nmID = trim((string)$item['nmID']);
                $price = (float)$item['price'];

                if ($nmID === '' || !ctype_digit($nmID)) {
                    continue;
                }

                Yii::$app->db->createCommand()->upsert(
                    '{{%wbcards_costs}}',
                    [
                        'load_date' => $date,
                        'nmID'      => $nmID,
                        'price'     => $price,
                    ],
                    [
                        'price' => $price,
                    ]
                )->execute();

                $processed++;
            }

            $transaction->commit();

            return [
                'success'   => true,
                'processed' => $processed,
                'message'   => "Сохранено позиций: {$processed}",
            ];
        } catch (\Throwable $e) {
            $transaction->rollBack();
            return ['success' => false, 'message' => 'Ошибка сохранения: ' . $e->getMessage()];
        }
    }

    /**
     * Страница просмотра и редактирования wbcards_costs.
     * Фильтр по датам и карточке берётся из getDPWidget (GET: date_from, date_to, nm_id).
     */
    public function actionList()
    {
        $params = getDPWidget::getParams();

        $query = (new Query())
            ->select([
                'c.id',
                'c.load_date',
                'c.nmID',
                'c.price',
                'w.title AS product_name',
                'w.vendorCode',
            ])
            ->from('{{%wbcards_costs}} c')
            ->leftJoin('{{%wbcards}} w', 'w.nmID = c.nmID')
            ->andWhere(['between', 'c.load_date', $params['date_from'], $params['date_to']])
            ->orderBy(['c.load_date' => SORT_DESC, 'c.nmID' => SORT_ASC]);

        if (!empty($params['nm_id'])) {
            $query->andWhere(['c.nmID' => $params['nm_id']]);
        }

        $rows = $query->all();

        return $this->render('list', [
            'rows' => $rows,
        ]);
    }

    /**
     * AJAX: обновление цены одной записи.
     * Ожидает POST: id, price
     */
    public function actionUpdatePrice()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $request = Yii::$app->request;

        $id = (int)$request->post('id');
        $priceRaw = trim((string)$request->post('price'));

        if ($id <= 0) {
            return ['success' => false, 'message' => 'Некорректный ID записи'];
        }

        $priceNormalized = str_replace(',', '.', $priceRaw);
        if ($priceRaw === '' || !is_numeric($priceNormalized) || (float)$priceNormalized < 0) {
            return ['success' => false, 'message' => 'Некорректная цена'];
        }

        $price = round((float)$priceNormalized, 2);

        try {
            $affected = Yii::$app->db->createCommand()->update(
                '{{%wbcards_costs}}',
                ['price' => $price],
                ['id' => $id]
            )->execute();
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => 'Ошибка сохранения: ' . $e->getMessage()];
        }

        if ($affected === 0) {
            return ['success' => false, 'message' => 'Запись не найдена'];
        }

        return ['success' => true, 'price' => $price];
    }

    /**
     * Возвращает значение ячейки по номеру колонки (1-based) и строки.
     * getCellByColumnAndRow() был удалён в PhpSpreadsheet 2.x, поэтому строим
     * координату вручную — так работает и в v1, и в v2.
     */
    private function cellValue(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $col, int $row)
    {
        $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        return $sheet->getCell($colLetter . $row)->getValue();
    }

    /**
     * Парсит xlsx/xls файл и находит нужные колонки по заголовкам первой строки.
     */
    private function parseFile(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();
        $highestColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(
            $sheet->getHighestColumn()
        );

        // --- Определяем заголовки в первой строке ---
        $nmIdCol = null;
        $priceCol = null;
        $nameCol = null; // колонка "Товар" — просто для наглядности в предпросмотре

        for ($col = 1; $col <= $highestColIndex; $col++) {
            $headerRaw = (string)$this->cellValue($sheet, $col, 1);
            $header = mb_strtolower(trim($headerRaw));

            if ($nmIdCol === null && in_array($header, self::NM_ID_HEADERS, true)) {
                $nmIdCol = $col;
                continue;
            }
            if ($priceCol === null && in_array($header, self::PRICE_HEADERS, true)) {
                $priceCol = $col;
                continue;
            }
            if ($nameCol === null && $header === 'товар') {
                $nameCol = $col;
            }
        }

        $items = [];
        $errors = [];

        if ($nmIdCol === null || $priceCol === null) {
            return compact('nmIdCol', 'priceCol', 'items', 'errors');
        }

        $seen = [];

        for ($row = 2; $row <= $highestRow; $row++) {
            $nmIdRaw = trim((string)$this->cellValue($sheet, $nmIdCol, $row));
            $priceRaw = trim((string)$this->cellValue($sheet, $priceCol, $row));
            $name = $nameCol !== null ? trim((string)$this->cellValue($sheet, $nameCol, $row)) : null;

            // Полностью пустая строка — пропускаем молча
            if ($nmIdRaw === '' && $priceRaw === '') {
                continue;
            }

            if ($nmIdRaw === '' || !ctype_digit($nmIdRaw)) {
                $errors[] = [
                    'row'    => $row,
                    'reason' => 'Некорректный или отсутствующий nmID',
                    'raw'    => $nmIdRaw,
                ];
                continue;
            }

            $priceNormalized = str_replace(',', '.', $priceRaw);
            if ($priceRaw === '' || !is_numeric($priceNormalized)) {
                $errors[] = [
                    'row'    => $row,
                    'reason' => 'Некорректная или отсутствующая цена',
                    'raw'    => $priceRaw,
                ];
                continue;
            }

            $price = (float)$priceNormalized;

            if (isset($seen[$nmIdRaw])) {
                // Дубликат nmID внутри файла — предупреждаем, но берём последнее значение
                $errors[] = [
                    'row'    => $row,
                    'reason' => "Дубликат nmID {$nmIdRaw} в файле (взято значение из строки {$row})",
                    'raw'    => $nmIdRaw,
                ];
            }

            $seen[$nmIdRaw] = true;

            $items[] = [
                'row'   => $row,
                'nmID'  => $nmIdRaw,
                'price' => $price,
                'name'  => $name,
            ];
        }

        // Убираем дубликаты по nmID из items, оставляя последнее вхождение
        $unique = [];
        foreach ($items as $item) {
            $unique[$item['nmID']] = $item;
        }
        $items = array_values($unique);

        return compact('nmIdCol', 'priceCol', 'items', 'errors');
    }
}
