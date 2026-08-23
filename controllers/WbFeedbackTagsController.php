<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;

/**
 * Разметка тегов из wb_feedback_tags как позитивных/негативных/нейтральных.
 */
class WbFeedbackTagsController extends Controller
{
    public function actionIndex()
    {
        $filter = Yii::$app->request->get('filter', 'unclassified'); // unclassified | all

        $query = (new \yii\db\Query())
            ->select(['id', 'tag_text', 'sentiment', 'usage_count'])
            ->from('wb_feedback_tags');

        if ($filter === 'unclassified') {
            $query->where(['sentiment' => 'neutral']);
        }

        $tags = $query->orderBy(['usage_count' => SORT_DESC])->all();

        return $this->render('index', [
            'tags' => $tags,
            'filter' => $filter,
        ]);
    }

    public function actionSetSentiment()
    {
        $request = Yii::$app->request;
        $id = $request->post('id');
        $sentiment = $request->post('sentiment');

        if (!in_array($sentiment, ['positive', 'negative', 'neutral'], true) || empty($id)) {
            throw new \yii\web\BadRequestHttpException('Некорректные данные.');
        }

        Yii::$app->db->createCommand()->update('wb_feedback_tags', [
            'sentiment' => $sentiment,
            'updated_at' => time(),
        ], ['id' => $id])->execute();

        return $this->redirect(Yii::$app->request->referrer ?: ['index']);
    }
}
