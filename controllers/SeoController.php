<?php
namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\data\ActiveDataProvider;
use yii\db\Expression;
use yii\helpers\Html;
use app\models\WbSeoRecommendation;
use app\models\WbSeoModel;

class SeoController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    [
                        'allow' => true,
                        'roles' => ['@'],
                        'matchCallback' => fn() => Yii::$app->user->can('viewSeo') || Yii::$app->user->can('admin'),
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'mark-viewed' => ['POST'],
                    'requeue' => ['POST'],
                    'process' => ['POST'],
                    'add-target' => ['POST'],
                    'remove-target' => ['POST'],
                ],
            ],
        ];
    }

    public function actionIndex($status = 'new')
    {
        $status = in_array($status, ['new','viewed']) ? $status : 'new';
        $query = WbSeoRecommendation::find()
            ->with(['card'])
            ->orderBy(['created_at' => SORT_DESC]);

        // фильтр по статусу
        $query->andWhere(['wb_seo_recommendation.status' => $status]);

        // company scope если не global
        $cm = Yii::$app->companyManager;
        if (!$cm->isGlobalMode() && $cm->getCurrentId()) {
            $query->andWhere(['wb_seo_recommendation.company_id' => $cm->getCurrentId()]);
        }

        // фильтр q: nmID / название / артикул продавца
        $q = trim((string)Yii::$app->request->get('q', ''));
        if ($q !== '') {
            if (ctype_digit($q)) {
                // цифры — ищем по nmID точным, или по vendorCode/title как подстроке
                $query->joinWith('card');
                $query->andWhere(['or',
                    ['wb_seo_recommendation.nmID' => (int)$q],
                    ['like', 'wbcards.vendorCode', $q],
                    ['like', 'wbcards.title', $q],
                ]);
            } else {
                $query->joinWith('card');
                $query->andWhere(['or',
                    ['like', 'wbcards.title', $q],
                    ['like', 'wbcards.vendorCode', $q],
                    ['like', 'wbcards.subjectName', $q],
                ]);
            }
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
        ]);

        $counts = [
            'new' => WbSeoRecommendation::find()->where(['status'=>'new'])->count(),
            'viewed' => WbSeoRecommendation::find()->where(['status'=>'viewed'])->count(),
        ];

        // блок "Необработанные" по кнопке около Сброс — если есть q, фильтруем по нему (заготов + необработанные)
        $unprocessed = [];
        if (Yii::$app->request->get('unprocessed')) {
            $spamDays = (int)(Yii::$app->params['seoAntiSpamDays'] ?? 14);
            $dateTo = date('Y-m-d', strtotime('-1 day'));
            $dateFrom = date('Y-m-d', strtotime('-30 days'));
            $qCond = '';
            $params = [':from'=>$dateFrom, ':to'=>$dateTo];
            if ($q !== '') {
                if (ctype_digit($q)) {
                    $qCond = " AND (w.nmID = :qNm OR w.vendorCode LIKE :qLike OR LOWER(w.title) LIKE LOWER(:qLike))";
                    $params[':qNm'] = (int)$q;
                    $params[':qLike'] = "%$q%";
                } else {
                    $qCond = " AND (LOWER(w.title) LIKE LOWER(:qLike) OR LOWER(w.vendorCode) LIKE LOWER(:qLike) OR LOWER(w.subjectName) LIKE LOWER(:qLike))";
                    $params[':qLike'] = "%$q%";
                }
            }
            $sql = "
                SELECT w.nmID, w.title, w.subjectName, w.brand, w.vendorCode, w.photos, COALESCE(s.total_qnt,0) as total_qnt
                FROM wbcards w
                LEFT JOIN (SELECT nm_id, SUM(qnt) as total_qnt FROM agg_daily_summary WHERE sdate BETWEEN :from AND :to GROUP BY nm_id) s ON s.nm_id=w.nmID
                WHERE 1=1 $qCond AND w.nmID NOT IN (
                    SELECT nmID FROM wb_seo_recommendation WHERE is_requeued=0 AND created_at >= DATE_SUB(NOW(), INTERVAL $spamDays DAY)
                )
                ORDER BY total_qnt DESC, w.nmID DESC LIMIT 20
            ";
            $unprocessed = Yii::$app->db->createCommand($sql, $params)->queryAll();
            $cm = Yii::$app->companyManager;
            if (!$cm->isGlobalMode() && $cm->getCurrentId()) {
                $unprocessed = array_values(array_filter($unprocessed, function($r) use ($cm){
                    $c = \app\models\WbCard::findOne(['nmID'=>$r['nmID']]);
                    return $c && (int)$c->company_id === (int)$cm->getCurrentId();
                }));
            }
        }

        // если нет рекомендаций и есть поиск — покажем карточки из wbcards с кнопкой Обработать
        $cardsProvider = null;
        $cards = [];
        if ($status === 'new' && $dataProvider->getTotalCount() === 0 && $q !== '') {
            $cardQuery = \app\models\WbCard::find();
            // поиск по wbcards — делаем глобально без company/is_active, чтобы находило как в Select2 (WbCard::ajaxSearch LIKE title)
            // иначе "заготов" не находится из-за скоупа компании
            // $cm = Yii::$app->companyManager; ... — убрано
            if (ctype_digit($q)) {
                $cardQuery->andWhere(['or',
                    ['nmID' => (int)$q],
                    ['like','vendorCode',$q],
                    ['like','title',$q],
                ]);
            } else {
                $cardQuery->andWhere(['or',
                    ['like','title',$q],
                    ['like','vendorCode',$q],
                    ['like','subjectName',$q],
                ]);
            }
            // не показываем карточки уже в обработке (anti-spam 14д)
            $spamDays = (int)(Yii::$app->params['seoAntiSpamDays'] ?? 14);
            $cardQuery->andWhere(['not in', 'nmID',
                (new \yii\db\Query())->select('nmID')->from('wb_seo_recommendation')
                    ->where(['is_requeued'=>0])
                    ->andWhere(['>=','created_at', new Expression("DATE_SUB(NOW(), INTERVAL $spamDays DAY)")])
            ]);
            $cardQuery->orderBy(['nmID'=>SORT_DESC]);
            $cardsProvider = new ActiveDataProvider([
                'query' => $cardQuery,
                'pagination' => ['pageSize'=>20],
            ]);
            $cards = $cardsProvider->getModels();
            // все id для "Обработать все" (без пагинации)
            $allIds = (clone $cardQuery)->select(['nmID'])->limit(100)->column();
        } else {
            $allIds = [];
        }

        return $this->render('index', [
            'dataProvider' => $dataProvider,
            'status' => $status,
            'counts' => $counts,
            'q' => $q,
            'cardsProvider' => $cardsProvider ?? null,
            'cards' => $cards ?? [],
            'allIds' => $allIds ?? [],
            'unprocessed' => $unprocessed ?? [],
        ]);
    }

    public function actionView($id)
    {
        $model = $this->findModel($id);
        $targets = \app\models\WbSeoTarget::find()->where(['nmID'=>$model->nmID])->orderBy(['priority'=>SORT_ASC])->all();
        return $this->render('view', ['model' => $model, 'targets'=>$targets]);
    }

    public function actionAddTarget()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $nmID = (int)Yii::$app->request->post('nmID');
        $phrase = trim((string)Yii::$app->request->post('phrase'));
        if (!$nmID || $phrase==='') return ['success'=>false,'error'=>'phrase required'];
        $phrase = mb_substr($phrase,0,500);
        $exists = \app\models\WbSeoTarget::find()->where(['nmID'=>$nmID,'phrase'=>$phrase])->exists();
        if ($exists) return ['success'=>false,'error'=>'Уже добавлена'];
        $m = new \app\models\WbSeoTarget();
        $m->nmID = $nmID;
        $m->phrase = $phrase;
        $m->priority = 10;
        $m->is_active = 1;
        $m->added_by = Yii::$app->user->id;
        $m->created_at = date('Y-m-d H:i:s');
        $m->updated_at = date('Y-m-d H:i:s');
        if (!$m->save()) return ['success'=>false,'error'=>json_encode($m->errors)];
        return ['success'=>true,'id'=>$m->id];
    }

    public function actionRemoveTarget()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $id = (int)Yii::$app->request->post('id');
        $m = \app\models\WbSeoTarget::findOne($id);
        if (!$m) return ['success'=>false,'error'=>'not found'];
        $m->delete();
        return ['success'=>true];
    }

    public function actionMarkViewed($id)
    {
        $model = $this->findModel($id);
        if ($model->status === 'new') {
            $model->status = 'viewed';
            $model->viewed_by = Yii::$app->user->id;
            $model->viewed_at = date('Y-m-d H:i:s');
            $model->is_requeued = 0;
            $model->requeued_at = null;
            $model->updated_at = date('Y-m-d H:i:s');
            $model->save(false);
            Yii::$app->session->setFlash('success', "Рекомендация #$id отмечена как просмотренная");
        }
        return $this->redirect(['view', 'id' => $id]);
    }

    public function actionRequeue($id)
    {
        $model = $this->findModel($id);
        // вернуть в обработку — снова new, снимем просмотр
        $model->status = 'new';
        $model->is_requeued = 1;
        $model->requeued_at = date('Y-m-d H:i:s');
        $model->viewed_by = null;
        $model->viewed_at = null;
        $model->updated_at = date('Y-m-d H:i:s');
        $model->save(false);
        Yii::$app->session->setFlash('success', "Товар {$model->nmID} возвращен в обработку — попадёт в приоритет при следующем анализе");
        return $this->redirect(['view', 'id' => $id]);
    }

    /**
     * Обработать с AI прямо из веба: генерирует новую рекомендацию для этого nmID.
     * POST /seo/process?nmID=123 — поддерживает AJAX (JSON) для попапа.
     */
    public function actionProcess()
    {
        $nmID = (int)Yii::$app->request->post('nmID', Yii::$app->request->get('nmID'));
        if (!$nmID) throw new \yii\web\BadRequestHttpException('nmID required');
        $card = \app\models\WbCard::findOne(['nmID' => $nmID]);
        if (!$card) throw new \yii\web\NotFoundHttpException("Карточка nmID $nmID не найдена");

        $companyId = $card->company_id ?: (Yii::$app->companyManager->getCurrentId() ?: 1);
        $company = \app\models\Company::findOne($companyId);
        $modelOverride = $company && $company->seo_model ? $company->seo_model : null;
        $days = 30;
        $dateTo = date('Y-m-d', strtotime('-1 day'));
        $dateFrom = date('Y-m-d', strtotime("-$days days"));

        set_time_limit(120);
        $svc = new \app\components\SeoAnalyzerService();
        $res = $svc->generate($nmID, (int)$companyId, $dateFrom, $dateTo, $modelOverride);
        if (!$res) {
            $err = $svc->lastError ?: 'unknown';
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ['success'=>false, 'error'=>$err];
            }
            Yii::$app->session->setFlash('error', 'AI не вернул результат: ' . $err);
            return $this->redirect(Yii::$app->request->referrer ?: ['index']);
        }

        $now = date('Y-m-d H:i:s');
        $rec = new WbSeoRecommendation();
        $rec->company_id = (int)$companyId;
        $rec->nmID = $nmID;
        $rec->old_title = $card->title;
        $rec->old_description = $card->description;
        $rec->new_title = $res['new_title'];
        $rec->new_description = $res['new_description'];
        $rec->rationale = $res['rationale'] ?? null;
        $rec->keywords_added = isset($res['keywords_added']) ? json_encode($res['keywords_added'], JSON_UNESCAPED_UNICODE) : null;
        $rec->keywords_removed = isset($res['keywords_removed']) ? json_encode($res['keywords_removed'], JSON_UNESCAPED_UNICODE) : null;
        $rec->confidence = isset($res['confidence']) ? (float)$res['confidence'] : null;
        $rec->model = $res['_used_model'] ?? Yii::$app->params['openRouterModel'] ?? null;
        $rec->prompt_tokens = $res['prompt_tokens'];
        $rec->completion_tokens = $res['completion_tokens'];
        $rec->raw_json = json_encode(['prompt'=>$res['_userData'],'response'=>$res['raw'],'raw_content'=>$res['raw_content']], JSON_UNESCAPED_UNICODE);
        $rec->status = 'new';
        $rec->is_requeued = 0;
        $rec->created_at = $now;
        $rec->updated_at = $now;
        if (!$rec->save()) {
            $err = json_encode($rec->errors, JSON_UNESCAPED_UNICODE);
            if (Yii::$app->request->isAjax) {
                Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
                return ['success'=>false, 'error'=>$err];
            }
            Yii::$app->session->setFlash('error', 'Ошибка сохранения: ' . $err);
            return $this->redirect(Yii::$app->request->referrer ?: ['index']);
        }
        if (Yii::$app->request->isAjax) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            return ['success'=>true, 'id'=>$rec->id, 'url'=>\yii\helpers\Url::to(['view','id'=>$rec->id])];
        }
        Yii::$app->session->setFlash('success', "Сгенерировано #{$rec->id} моделью {$rec->model}");
        return $this->redirect(['view', 'id' => $rec->id]);
    }

    protected function findModel($id): WbSeoRecommendation
    {
        $m = WbSeoRecommendation::findOne((int)$id);
        if (!$m) throw new \yii\web\NotFoundHttpException("Рекомендация $id не найдена");
        return $m;
    }
}
