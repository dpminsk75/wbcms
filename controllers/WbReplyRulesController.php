<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use app\models\WbReplyRule;
use app\models\WbReplyTemplatePart;
use yii\data\ActiveDataProvider;
//use yii\helpers\Model;

class WbReplyRulesController extends Controller
{
    public function behaviors()
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'delete' => ['POST'],
                ],
            ],
        ];
    }

    /**
     * Экран 1: Список всех правил (Аналог первого скриншота)
     */
    public function actionIndex()
    {
        $dataProvider = new ActiveDataProvider([
            'query' => WbReplyRule::find()->orderBy(['updated_at' => SORT_DESC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);

        return $this->render('index', [
            'dataProvider' => $dataProvider,
        ]);
    }


/**
     * AJAX-поиск товаров для Select2
     */
    public function actionProductList($q = null, $id = null)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        
        if (!is_null($q)) {
            $data = (new \yii\db\Query())
                // Форсируем приведение nmID к строке через CAST на уровне СУБД
                ->select([
                    'id' => new \yii\db\Expression("CAST(nmID AS CHAR)"), 
                    'text' => new \yii\db\Expression("CONCAT('[', nmID, '] ', title)")
                ])
                ->from('wbcards')
                ->where(['like', 'title', $q])
                ->orWhere(['like', 'nmID', $q])
                ->limit(20)
                ->all();
                
            $out['results'] = array_values($data);
        }
        
        return $out;
    }

/**
     * AJAX-поиск брендов для Select2
     */
    public function actionBrandList($q = null)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $out = ['results' => ['id' => '', 'text' => '']];
        
        if (!is_null($q)) {
            $data = (new \yii\db\Query())
                ->select(['id' => 'brand', 'text' => 'brand'])
                ->from('wbcards')
                ->where(['like', 'brand', $q])
                ->andWhere(['not', ['brand' => null]])
                ->andWhere(['not', ['brand' => '']])
                ->distinct()
                ->limit(20)
                ->all();
                
            $out['results'] = array_values($data);
        }
        
        return $out;
    }

public function actionCreate()
    {
        $model = new WbReplyRule();
        
        $greetings = [new WbReplyTemplatePart(['part_type' => WbReplyTemplatePart::TYPE_GREETING])];
        $bodies = [new WbReplyTemplatePart(['part_type' => WbReplyTemplatePart::TYPE_BODY])];
        $signoffs = [new WbReplyTemplatePart(['part_type' => WbReplyTemplatePart::TYPE_SIGNOFF])];

        if ($model->load(Yii::$app->request->post())) {
            $post = Yii::$app->request->post();
            
            // Сбор текстовых блоков
            $parseParts = function($postKey, $type) use ($post) {
                $result = [];
                if (!empty($post['WbReplyTemplatePart'][$postKey])) {
                    foreach ($post['WbReplyTemplatePart'][$postKey] as $data) {
                        if (!empty($data['text'])) {
                            $result[] = new WbReplyTemplatePart(['part_type' => $type, 'text' => $data['text']]);
                        }
                    }
                }
                return $result;
            };

            $greetings = $parseParts('greetings', WbReplyTemplatePart::TYPE_GREETING);
            $bodies = $parseParts('bodies', WbReplyTemplatePart::TYPE_BODY);
            $signoffs = $parseParts('signoffs', WbReplyTemplatePart::TYPE_SIGNOFF);
            $allParts = array_merge($greetings, $bodies, $signoffs);

            $valid = $model->validate();
            foreach ($allParts as $part) {
                $valid = $part->validate() && $valid;
            }

            if ($valid) {
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    if ($model->save(false)) {
                        foreach ($allParts as $part) {
                            $part->rule_id = $model->id;
                            $part->save(false);
                        }
                        
                        // Сохраняем бренды
                        if ($model->rule_type === 'brand' && !empty($post['selected_brands'])) {
                            foreach ($post['selected_brands'] as $brand) {
                                Yii::$app->db->createCommand()->insert('wb_reply_rule_brands', [
                                    'rule_id' => $model->id,
                                    'brand_name' => $brand
                                ])->execute();
                            }
                        }

                        // Сохраняем товары
                        if ($model->rule_type === 'product' && !empty($post['selected_products'])) {
                            foreach ($post['selected_products'] as $nmID) {
                                Yii::$app->db->createCommand()->insert('wb_reply_rule_products', [
                                    'rule_id' => $model->id,
                                    'nmID' => $nmID
                                ])->execute();
                            }
                        }

                        $transaction->commit();
                        Yii::$app->session->setFlash('success', 'Правило успешно сохранено.');
                        return $this->redirect(['index']);
                    }
                } catch (\Exception $e) {
                    $transaction->rollBack();
                    Yii::$app->session->setFlash('error', 'Ошибка при сохранении: ' . $e->getMessage());
                }
            }
        }

        return $this->render('create', [
            'model' => $model,
            'greetings' => $greetings,
            'bodies' => $bodies,
            'signoffs' => $signoffs,
        ]);
    }

    public function actionUpdate($id)
    {
        $model = WbReplyRule::findOne($id);
        if ($model === null) {
            throw new \yii\web\NotFoundHttpException('Запрошенная страница не существует.');
        }

        $greetings = WbReplyTemplatePart::find()->where(['rule_id' => $id, 'part_type' => WbReplyTemplatePart::TYPE_GREETING])->all();
        $bodies = WbReplyTemplatePart::find()->where(['rule_id' => $id, 'part_type' => WbReplyTemplatePart::TYPE_BODY])->all();
        $signoffs = WbReplyTemplatePart::find()->where(['rule_id' => $id, 'part_type' => WbReplyTemplatePart::TYPE_SIGNOFF])->all();

        if (empty($greetings)) { $greetings = [new WbReplyTemplatePart(['part_type' => WbReplyTemplatePart::TYPE_GREETING])]; }
        if (empty($bodies)) { $bodies = [new WbReplyTemplatePart(['part_type' => WbReplyTemplatePart::TYPE_BODY])]; }
        if (empty($signoffs)) { $signoffs = [new WbReplyTemplatePart(['part_type' => WbReplyTemplatePart::TYPE_SIGNOFF])]; }

        // Получаем уже сохраненные бренды и товары для передачи во View формы
        $selectedBrands = (new \yii\db\Query())->select('brand_name')->from('wb_reply_rule_brands')->where(['rule_id' => $id])->column();

        $selectedProducts = (new \yii\db\Query())->select('nmID')->from('wb_reply_rule_products')->where(['rule_id' => $id])->column();
        $selectedProducts = array_map('strval', $selectedProducts);

        if ($model->load(Yii::$app->request->post())) {
            $post = Yii::$app->request->post();
            
            $parseParts = function($postKey, $type) use ($post) {
                $result = [];
                if (!empty($post['WbReplyTemplatePart'][$postKey])) {
                    foreach ($post['WbReplyTemplatePart'][$postKey] as $data) {
                        if (!empty($data['text'])) {
                            $result[] = new WbReplyTemplatePart(['part_type' => $type, 'text' => $data['text']]);
                        }
                    }
                }
                return $result;
            };

            $greetings = $parseParts('greetings', WbReplyTemplatePart::TYPE_GREETING);
            $bodies = $parseParts('bodies', WbReplyTemplatePart::TYPE_BODY);
            $signoffs = $parseParts('signoffs', WbReplyTemplatePart::TYPE_SIGNOFF);
            $allParts = array_merge($greetings, $bodies, $signoffs);

            $valid = $model->validate();
            foreach ($allParts as $part) {
                $valid = $part->validate() && $valid;
            }

            if ($valid) {
                $transaction = Yii::$app->db->beginTransaction();
                try {
                    if ($model->save(false)) {
                        WbReplyTemplatePart::deleteAll(['rule_id' => $model->id]);
                        foreach ($allParts as $part) {
                            $part->rule_id = $model->id;
                            $part->save(false);
                        }
                        
                        // Перезаписываем бренды
                        Yii::$app->db->createCommand()->delete('wb_reply_rule_brands', ['rule_id' => $model->id])->execute();
                        if ($model->rule_type === 'brand' && !empty($post['selected_brands'])) {
                            foreach ($post['selected_brands'] as $brand) {
                                Yii::$app->db->createCommand()->insert('wb_reply_rule_brands', [
                                    'rule_id' => $model->id,
                                    'brand_name' => $brand
                                ])->execute();
                            }
                        }

                        // Перезаписываем товары
                        Yii::$app->db->createCommand()->delete('wb_reply_rule_products', ['rule_id' => $model->id])->execute();
                        if ($model->rule_type === 'product' && !empty($post['selected_products'])) {
                            foreach ($post['selected_products'] as $nmID) {
                                Yii::$app->db->createCommand()->insert('wb_reply_rule_products', [
                                    'rule_id' => $model->id,
                                    'nmID' => $nmID
                                ])->execute();
                            }
                        }

                        $transaction->commit();
                        Yii::$app->session->setFlash('success', 'Правило успешно обновлено.');
                        return $this->redirect(['index']);
                    }
                } catch (\Exception $e) {
                    $transaction->rollBack();
                    Yii::$app->session->setFlash('error', 'Ошибка при обновлении: ' . $e->getMessage());
                }
            }
        }

        return $this->render('update', [
            'model' => $model,
            'greetings' => $greetings,
            'bodies' => $bodies,
            'signoffs' => $signoffs,
            'selectedBrands' => $selectedBrands,
            'selectedProducts' => $selectedProducts,
        ]);
    }


/**
     * Экран тестовой генерации ответов на 100 последних отзывов
     */
    public function actionTestGeneration()
    {
        // 1. Выбираем 100 последних отзывов и сразу джойним wbcards
        $feedbacks = (new \yii\db\Query())
            ->select([
                'f.*',
                'card_title' => 'c.title',
                'card_brand' => 'c.brand'
            ])
            ->from(['f' => 'wb_feedbacks'])
            ->leftJoin(['c' => 'wbcards'], 'c.nmID = f.nmID') // По правилам проекта c.nmID
            ->orderBy(['f.created_at' => SORT_DESC]) // Безопасная сортировка по ID, если нет created_at
            ->limit(100)
            ->all();

        // 2. Загружаем все активные правила автоответов
        $rules = WbReplyRule::find()->where(['is_active' => 1])->all();

        $results = [];

        foreach ($feedbacks as $fb) {
            $matchedRule = null;
            $generatedText = '— Не удалось подобрать правило —';
            
//            $fbRating = (int)($fb['valuation'] ?? $fb['rating'] ?? 5);
            $fbRating = (int)($fb['productValuation'] ?? 5);

            $fbText = trim($fb['text'] ?? '');
            $hasText = !empty($fbText);
            $fbNmId = $fb['nmID'] ?? null;
            $fbBrand = $fb['card_brand'] ?? null;

            // Ищем подходящее правило по приоритетам
            $bestRules = [];
            foreach ($rules as $rule) {
                // Проверяем базовые условия: Рейтинг и Текст
                if ($fbRating < $rule->rating_min || $fbRating > $rule->rating_max) {
                    continue;
                }
                
                if ($rule->text_condition === 'with_text' && !$hasText) {
                    continue;
                }
                if ($rule->text_condition === 'no_text' && $hasText) {
                    continue;
                }

                // Проверяем тип правила и связи
                if ($rule->rule_type === 'product') {
                    $isSavedProduct = (new \yii\db\Query())
                        ->from('wb_reply_rule_products')
                        ->where(['rule_id' => $rule->id, 'nmID' => $fbNmId])
                        ->exists();
                    if ($isSavedProduct) {
                        $bestRules[1][] = $rule;
                    }
                } elseif ($rule->rule_type === 'brand') {
                    if (!empty($fbBrand)) {
                        $isSavedBrand = (new \yii\db\Query())
                            ->from('wb_reply_rule_brands')
                            ->where(['rule_id' => $rule->id, 'brand_name' => $fbBrand])
                            ->exists();
                        if ($isSavedBrand) {
                            $bestRules[2][] = $rule;
                        }
                    }
                } else {
                    // Общее правило
                    $bestRules[3][] = $rule;
                }
            }

            // Выбираем правило с наивысшим приоритетом
            if (!empty($bestRules[1])) {
                $matchedRule = $bestRules[1][0];
            } elseif (!empty($bestRules[2])) {
                $matchedRule = $bestRules[2][0];
            } elseif (!empty($bestRules[3])) {
                $matchedRule = $bestRules[3][0];
            }

            // 3. Если правило найдено — генерируем ответ из случайных частей

// 3. Если правило найдено — генерируем ответ
            if ($matchedRule) {
                $greetingsModels = WbReplyTemplatePart::find()->where(['rule_id' => $matchedRule->id, 'part_type' => WbReplyTemplatePart::TYPE_GREETING])->all();
                $bodiesModels = WbReplyTemplatePart::find()->where(['rule_id' => $matchedRule->id, 'part_type' => WbReplyTemplatePart::TYPE_BODY])->all();
                $signoffsModels = WbReplyTemplatePart::find()->where(['rule_id' => $matchedRule->id, 'part_type' => WbReplyTemplatePart::TYPE_SIGNOFF])->all();

                $greetings = \yii\helpers\ArrayHelper::getColumn($greetingsModels, 'text');
                $bodies = \yii\helpers\ArrayHelper::getColumn($bodiesModels, 'text');
                $signoffs = \yii\helpers\ArrayHelper::getColumn($signoffsModels, 'text');

                $parts = [];
                
                // Проверяем наличие имени покупателя
                $userName = trim($fb['userName'] ?? '');
                $hasName = !empty($userName);

                // Умный выбор приветствия
                if (!empty($greetings)) {
                    $fallbackGreetings = [];
                    $specialGreetings = [];

                    foreach ($greetings as $greetText) {
                        if (stripos($greetText, '{{без_имени}}') !== false) {
                            $specialGreetings[] = str_replace('{{без_имени}}', '', $greetText);
                        } else {
                            $fallbackGreetings[] = $greetText;
                        }
                    }

                    if (!$hasName && !empty($specialGreetings)) {
                        // Имени нет, и у нас есть заготовки "для безымянных"
                        $parts[] = $specialGreetings[array_rand($specialGreetings)];
                    } else if (!$hasName) {
                        // Имени нет и специальных заготовок нет — берем обычное, потом вырежем тег {{имя}}
                        $parts[] = !empty($fallbackGreetings) ? $fallbackGreetings[array_rand($fallbackGreetings)] : '';
                    } else {
                        // Имя есть — берем строго обычные приветствия (чтобы не выдать безымянное живому человеку)
                        $parts[] = !empty($fallbackGreetings) ? $fallbackGreetings[array_rand($fallbackGreetings)] : '';
                    }
                }

                // Берем случайное тело ответа
                if (!empty($bodies)) {
                    $parts[] = $bodies[array_rand($bodies)];
                }
                // Берем случайное прощание
                if (!empty($signoffs)) {
                    $parts[] = $signoffs[array_rand($signoffs)];
                }

                // Определяем разделитель
                $separator = " ";
                if ($matchedRule->part_separator === 'newline') {
                    $separator = "\n";
                } elseif ($matchedRule->part_separator === 'paragraph') {
                    $separator = "\n\n";
                }

                $generatedText = implode($separator, array_filter($parts));

                // Финальная подстановка переменных в собранный текст
                if ($hasName) {
                    $generatedText = str_ireplace('{{имя}}', $userName, $generatedText);
                } else {
                    // Если имени нет, очищаем тег {{имя}} и убираем возможные двойные пробелы
                    $generatedText = str_ireplace('{{имя}}', '', $generatedText);
                    $generatedText = str_replace('  ', ' ', $generatedText);
                }
            }

            $results[] = [
                'feedback' => $fb,
                'matched_rule' => $matchedRule,
                'generated_text' => $generatedText,
            ];
        }

        return $this->render('test-generation', [
            'results' => $results,
        ]);
    }


/**
     * Удаление правила автоответа и всех его зависимостей
     */
    public function actionDelete($id)
    {
        $model = WbReplyRule::findOne($id);
        if ($model === null) {
            throw new NotFoundHttpException('Правило автоответа не найдено.');
        }

        $transaction = Yii::$app->db->beginTransaction();
        try {
            // 1. Удаляем связанные шаблоны текста (приветствия, тела, прощания)
            WbReplyTemplatePart::deleteAll(['rule_id' => $model->id]);

            // 2. Удаляем связи с товарами и брендами
            Yii::$app->db->createCommand()->delete('wb_reply_rule_products', ['rule_id' => $model->id])->execute();
            Yii::$app->db->createCommand()->delete('wb_reply_rule_brands', ['rule_id' => $model->id])->execute();

            // 3. Удаляем само базовое правило
            $model->delete();

            $transaction->commit();
            Yii::$app->session->setFlash('success', 'Правило автоответа успешно удалено.');
        } catch (\Exception $e) {
            $transaction->rollBack();
            Yii::$app->session->setFlash('error', 'Ошибка при удалении: ' . $e->getMessage());
        }

        return $this->redirect(['index']);
    }

}