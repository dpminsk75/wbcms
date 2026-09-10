# План доработки складского учета — последовательно

> Сохранить в `md` — да, в репо как `STOCK_ROADMAP.md` (рядом с `ADMIN_SUMMARY.md`), чтобы видно в git и на проде.

## 0. Подготовка (1 день)
- [ ] Зафиксировать текущие остатки: `wb_central_stock` + `wb_virtual_stock` → выгрузка в Excel (бэкап).
- [ ] Вкл. `fbs_deduct_test=1` на проде, проверить лог `/wb-fbs-virtual/deduct-log`.

## 1. Фундамент — единый остаток (2 дня)
- [ ] Миграция `wb_stock_balance (company_id, warehouseId, sku, nmID, chrtID, quantity)` PK `(company,warehouse,sku)`.
- [ ] `wb_stock_ledger (id, doc_type, doc_id, warehouseId, sku, delta, before, after, user_id, created_at)`.
- [ ] `wb_fbs_warehouse.type = central|virtual|physical` + `consider_orders`, миграция данных.
- [ ] `StockService` — единственное место `UPDATE balance` в транзакции, запись в `ledger`.

## 2. Перевод виртуалки (1 день)
- [ ] `wb-fbs-virtual/index` читать `wb_stock_balance WHERE warehouseId = virtual_default` вместо `wb_virtual_stock`.
- [ ] `wb_fbs_warehouse.is_virtual=1` → `type=virtual`, старый `wb_virtual_stock` оставить view на месяц.

## 3. Документы — приход/расход (2 дня)
- [ ] `wb_doc (id, company_id, type, status draft/posted/canceled, date, comment, user_id)` + `wb_doc_item (doc_id, sku, qty)`.
- [ ] Типы `RECEIPT` (на ЦС) и `EXPENSE` (списание с ВС, причина обязательна).
- [ ] UI `/stock/doc` — список, создание, `Провести/Отменить` → `StockService::post()`.

## 4. Перемещение (1 день)
- [ ] `TRANSFER from→to` — атомарно `ЦС -50 / ВС +50`, две записи `ledger`, проверка `qty_before >= qty`.
- [ ] UI перемещения, запрет `from==to` и минуса.

## 5. Авто-списание по заказам (1 день)
- [ ] `consider_orders=1` — `wb-orders-fbs/sync` создаёт системный `EXPENSE fbs_order` (дедуп `is_deducted`), проводка как в п.3, `PUT` только при `fbs_deduct_test=0`.

## 6. Инвентаризация (2 дня)
- [ ] `INVENTORY` — таблично `qty_book` (из `balance`) + ввод `qty_fact` → `delta` → авто-`EXPENSE/RECEIPT` при проведении.
- [ ] Акт печать.

## 7. Оприходование излишков + корректировки (0.5 дня)
- [ ] `ADJUSTMENT +/-` — ручное «нашли/потеряли».

## 8. Права и меню (0.5 дня) — уже частично готово
- [ ] Роль `fbsManager`/`stockManager` → `manageFbsStocks`, `MenuHelper Склад`, `AccessControl` в контроллерах, дашборд `isFbsOnly`.

## 9. Выгрузка и отчёт (0.5 дня)
- [ ] `wb-fbs-virtual` выгрузка теперь `SELECT quantity FROM wb_stock_balance WHERE warehouseId=virtual_default`, экспорт уже с `hiddenFromExport`.

## 10. Тест и накат (1 день)
- [ ] Прогнать `TEST MODE` на проде, сверить `ledger` и `deduct-log`, `FULL` ночью, `FAST` каждые 5 мин `wb-sync-all`.
- [ ] Инструкция для кладовщика (простым языком).

**Порядок важен:** 1→2→3→4→5→6 — каждый шаг опирается на предыдущий, без прыжков. Оценка `~10 дней` одним бэком.
