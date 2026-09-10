# Склад — коммит

Дорожная карта: `STOCK_ROADMAP.md` п.1-8 закрыты (п.9-10 в работе).

## Что сделано (последняя сессия)
- Перемещение `TRANSFER` (2 склада), Инвентаризация `INVENTORY` (учёт/факт/дельта, Excel, vendorCode выбор), Корректировка `ADJUSTMENT` (+/-), права `isFbsOnly`, дашборд.
- Правки шапки 50/50, итоги, экспорт Excel, `kartik` для `view/index`, исправлен `view` posted (N+1, pjax false).
- Редактирование/удаление черновиков (`draft/canceled` → `draft`), view экспорт.

## Файлы к коммиту
```
migrations/m260909_000002_stock_transfer.php
migrations/m260909_000003_stock_inventory.php
migrations/m260828_000002_fbs_stock_role.php
migrations/m260909_000001_fbs_stock_role_update.php
models/WbDoc.php
models/WbDocItem.php
models/OurWarehouse.php
services/StockService.php
controllers/WbDocController.php
controllers/OurWarehouseController.php
components/MenuHelper.php
views/wb-doc/create.php
views/wb-doc/update.php      # новый
views/wb-doc/index.php
views/wb-doc/view.php
views/wb-doc/print.php       # новый
views/site/index_dashboard.php
```

Удалить мусор: `migrations/m260909_000001_fbs_stock_role.xxx`

## SQL на проде (если миграции не накатывал)
```sql
ALTER TABLE wb_doc ADD COLUMN to_warehouseId BIGINT NULL AFTER warehouseId;
ALTER TABLE wb_doc_item ADD COLUMN qty_before INT NULL AFTER qty, ADD COLUMN qty_fact INT NULL AFTER qty_before;
```

## Команды git (PowerShell, из D:\OpenCode\wbcms)
```powershell
& "C:\Program Files\Git\cmd\git.exe" status
& "C:\Program Files\Git\cmd\git.exe" diff --stat
& "C:\Program Files\Git\cmd\git.exe" add migrations/m260909_000002_stock_transfer.php migrations/m260909_000003_stock_inventory.php migrations/m260828_000002_fbs_stock_role.php migrations/m260909_000001_fbs_stock_role_update.php models/WbDoc.php models/WbDocItem.php models/OurWarehouse.php services/StockService.php controllers/WbDocController.php controllers/OurWarehouseController.php components/MenuHelper.php views/wb-doc/create.php views/wb-doc/update.php views/wb-doc/index.php views/wb-doc/view.php views/wb-doc/print.php views/site/index_dashboard.php STOCK_ROADMAP.md STOCK_COMMIT.md
& "C:\Program Files\Git\cmd\git.exe" status
& "C:\Program Files\Git\cmd\git.exe" commit -m "stock: transfer/inventory/adjustment, edit/cancel, kartik view, header 50/50, excel export"
& "C:\Program Files\Git\cmd\git.exe" log --oneline -3
& "C:\Program Files\Git\cmd\git.exe" push
# если ветка не пушилась:
# & "C:\Program Files\Git\cmd\git.exe" push -u origin HEAD
```

## Проверка локально
```powershell
php -l controllers/WbDocController.php; php -l views/wb-doc/create.php; php -l views/wb-doc/view.php
```
