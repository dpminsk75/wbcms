<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\filters\VerbFilter;
use yii\filters\AccessControl;

/**
 * Web-обёртка для консольной команды wb-fbs/sync-warehouses
 * GET https://marketplace-api.wildberries.ru/api/v3/warehouses
 */
class WbFbsController extends Controller
{
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'roles' => ['manageFbsStocks']],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'sync-warehouses' => ['post'],
                ],
            ],
        ];
    }

    public function actionSyncWarehouses()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $companyId = Yii::$app->companyManager->getCurrentId();
        $isGlobal = Yii::$app->companyManager->isGlobalMode();

        if ($isGlobal) {
            // в веб-контексте sync для всех активных компаний как в console
            $companies = Yii::$app->companyManager->getActiveCompanies();
        } else {
            $company = (new \yii\db\Query())->from('companies')->where(['id' => $companyId])->one();
            $companies = $company ? [$company] : [];
        }

        if (empty($companies)) {
            return ['success' => false, 'error' => 'Нет активных компаний'];
        }

        $total = 0;
        $errors = [];

        foreach ($companies as $company) {
            $cid = (int)($company['id'] ?? $companyId);
            $token = $company['api_key'] ?? null;
            if (!$token) {
                $errors[] = ($company['name'] ?? $cid) . ': нет api_key';
                continue;
            }

            try {
                $response = Yii::$app->wbHttpClient->get(
                    'https://marketplace-api.wildberries.ru/api/v3/warehouses',
                    [],
                    $token,
                    $cid
                );
            } catch (\Throwable $e) {
                $errors[] = ($company['name'] ?? $cid) . ': ' . $e->getMessage();
                continue;
            }

            if (!$response->isOk) {
                $errors[] = ($company['name'] ?? $cid) . ': HTTP ' . $response->statusCode . ' ' . substr($response->content, 0, 300);
                continue;
            }

            $data = $response->data;
            $warehouses = is_array($data) && isset($data['warehouses']) ? $data['warehouses'] : (is_array($data) ? $data : []);

            foreach ($warehouses as $wh) {
                $wId = $wh['id'] ?? $wh['warehouseId'] ?? null;
                if ($wId === null) {
                    continue;
                }
                $name = $wh['name'] ?? $wh['warehouseName'] ?? "Склад $wId";
                $address = $wh['address'] ?? null;
                $officeId = $wh['officeId'] ?? null;
                $isDeleting = (int)(bool)($wh['isDeleting'] ?? $wh['is_deleting'] ?? 0);
                $isProcessing = (int)(bool)($wh['isProcessing'] ?? $wh['is_processing'] ?? 1);

                $row = [
                    'company_id'    => $cid,
                    'warehouseId'   => (int)$wId,
                    'name'          => $name,
                    'address'       => $address,
                    'officeId'      => $officeId !== null ? (int)$officeId : null,
                    'isActive'      => 1,
                    'is_virtual'    => 0,
                    'is_deleting'   => $isDeleting,
                    'is_processing' => $isProcessing,
                    'raw_json'      => json_encode($wh, JSON_UNESCAPED_UNICODE),
                ];

                Yii::$app->db->createCommand()->upsert('wb_fbs_warehouse', $row, [
                    'name'          => $row['name'],
                    'address'       => $row['address'],
                    'officeId'      => $row['officeId'],
                    'isActive'      => 1,
                    'is_deleting'   => $row['is_deleting'],
                    'is_processing' => $row['is_processing'],
                    'raw_json'      => $row['raw_json'],
                ])->execute();
                $total++;
            }
        }

        if ($total === 0 && !empty($errors)) {
            return ['success' => false, 'error' => implode("; ", $errors)];
        }

        return ['success' => true, 'total' => $total, 'errors' => $errors];
    }
}
