create table "bill_items" ("id" integer primary key autoincrement not null, "created_at" datetime, "updated_at" datetime)

select name, type, not "notnull" as "nullable", dflt_value as "default", pk as "primary", hidden as "extra" from pragma_table_xinfo('bill_items', 'main') order by cid asc

select "sql" from "main".sqlite_master where type = 'table' and name = 'bill_items'

