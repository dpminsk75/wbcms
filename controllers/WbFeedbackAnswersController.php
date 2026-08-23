<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\data\SqlDataProvider;
use yii\helpers\Json;

/**
 * Представление: какие отзывы получили ответ, каким правилом и что именно отправлено.
 * Без тегов и стоп-слов — это отдельная фича, которую пока не подключаем.
 */
class WbFeedbackAnswersController extends Controller
{
    public function actionIndex()
    {
        $request = Yii::$app->request;

        // ---- Фильтры из GET ----
        $companyId = $request->get('company_id');
        $nmID = $request->get('nmID');
        $rating = $request->get('rating');
        // status: '' = все, 'answered' = есть ответ, 'not_answered' = нет ответа
        $status = $request->get('status', '');

        // По умолчанию — последние 3 дня (включая сегодня)
        $dateFrom = $request->get('dateFrom', date('Y-m-d', strtotime('-2 days')));
        $dateTo = $request->get('dateTo', date('Y-m-d'));

        $hasMedia = $request->get('hasMedia'); // '1' = только с фото/видео
        $paidOnly = $request->get('paidOnly'); // '1' = только платные (f_cost > 0)
        $hideAnswers = $request->get('hideAnswers'); // '1' = не выводить колонку "Ответ"

        $where = ['1=1'];
        $params = [];

        if (!empty($companyId)) {
            $where[] = 'f.company_id = :company_id';
            $params[':company_id'] = $companyId;
        }
        if (!empty($nmID)) {
            $where[] = 'f.nmID = :nmID';
            $params[':nmID'] = $nmID;
        }
        if ($rating !== null && $rating !== '') {
            $where[] = 'f.productValuation = :rating';
            $params[':rating'] = (int)$rating;
        }
        if ($status === 'answered') {
            $where[] = "(f.answer IS NOT NULL AND f.answer != '' AND f.answer != 'null')";
        } elseif ($status === 'not_answered') {
            $where[] = "(f.answer IS NULL OR f.answer = '' OR f.answer = 'null')";
        }
        if (!empty($dateFrom)) {
            $where[] = 'f.createdDate >= :dateFrom';
            $params[':dateFrom'] = $dateFrom . ' 00:00:00';
        }
        if (!empty($dateTo)) {
            $where[] = 'f.createdDate <= :dateTo';
            $params[':dateTo'] = $dateTo . ' 23:59:59';
        }
        if ($hasMedia === '1') {
            $where[] = "(
                (f.photoLinks IS NOT NULL AND f.photoLinks NOT IN ('', 'null', '[]'))
                OR (f.video IS NOT NULL AND f.video NOT IN ('', 'null', '{}'))
            )";
        }
        if ($paidOnly === '1') {
            $where[] = 'f.f_cost > 0';
        }

        $whereSql = implode(' AND ', $where);

        $sql = "
            SELECT
                f.id,
                f.nmID,
                f.userName,
                f.productValuation,
                f.text,
                f.pros,
                f.cons,
                f.answer,
                f.is_auto_replied,
                f.rule_id,
                f.createdDate,
                f.updatedDate,
                f.photoLinks,
                f.video,
                f.f_cost,
                f.bables,
                c.title AS product_title,
                rr.title AS rule_title
            FROM wb_feedbacks f
            LEFT JOIN wbcards c ON c.nmID = f.nmID
            LEFT JOIN wb_reply_rules rr ON rr.id = f.rule_id
            WHERE {$whereSql}
        ";

        $countSql = "SELECT COUNT(*) FROM wb_feedbacks f WHERE {$whereSql}";

        $dataProvider = new SqlDataProvider([
            'sql' => $sql,
            'params' => $params,
            'totalCount' => (int)Yii::$app->db->createCommand($countSql, $params)->queryScalar(),
            'sort' => [
                'attributes' => [
                    'createdDate' => [
                        'asc' => ['f.createdDate' => SORT_ASC],
                        'desc' => ['f.createdDate' => SORT_DESC],
                        'default' => SORT_DESC,
                        'label' => 'Дата отзыва',
                    ],
                    'productValuation' => [
                        'asc' => ['f.productValuation' => SORT_ASC],
                        'desc' => ['f.productValuation' => SORT_DESC],
                        'label' => 'Оценка',
                    ],
                ],
                'defaultOrder' => ['createdDate' => SORT_DESC],
            ],
            'pagination' => [
                'pageSize' => 30,
            ],
        ]);

        // Списки для фильтров
        $companies = (new \yii\db\Query())
            ->select(['id', 'name'])
            ->from('companies')
            ->where(['is_active' => 1])
            ->all();

        // Используем тот же способ получения списка карточек, что и в остальном проекте
        // (см. dp-view.php / getDPWidget.php), чтобы Select2 вёл себя привычно.
        if (class_exists('\app\models\WbCard') && method_exists('\app\models\WbCard', 'getListForSelect')) {
            $cardsList = \app\models\WbCard::getListForSelect();
        } else {
            $cardsList = \yii\helpers\ArrayHelper::map(
                (new \yii\db\Query())
                    ->select(['nmID', 'title'])
                    ->from('wbcards')
                    ->orderBy(['title' => SORT_ASC])
                    ->all(),
                'nmID',
                function ($row) {
                    return ($row['title'] ?: '(без названия)') . ' — ' . $row['nmID'];
                }
            );
        }

        $rules = (new \yii\db\Query())
            ->select(['id', 'title'])
            ->from('wb_reply_rules')
            ->all();
        $rulesById = \yii\helpers\ArrayHelper::map($rules, 'id', 'title');

        $tagsSentiment = \yii\helpers\ArrayHelper::map(
            (new \yii\db\Query())->select(['tag_text', 'sentiment'])->from('wb_feedback_tags')->all(),
            'tag_text',
            'sentiment'
        );

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'companies' => $companies,
            'cardsList' => $cardsList,
            'rulesById' => $rulesById,
            'tagsSentiment' => $tagsSentiment,
            'filter' => [
                'company_id' => $companyId,
                'nmID' => $nmID,
                'rating' => $rating,
                'status' => $status,
                'dateFrom' => $dateFrom,
                'dateTo' => $dateTo,
                'hasMedia' => $hasMedia,
                'paidOnly' => $paidOnly,
                'hideAnswers' => $hideAnswers,
            ],
        ]);
    }
}
