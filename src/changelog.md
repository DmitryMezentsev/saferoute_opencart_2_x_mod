v3.1
----
* Добавлена поддержка купонов.

v3.0
----
Версия с новым виджетом SafeRoute.
* Добавлена поддержка габаритов товаров.

[Инструкция по установке модуля](https://saferoute.atlassian.net/wiki/spaces/modules/pages/18350148/)

----
Инструкция по обновлению с v2.0.
1. Заменить все файлы старого модуля на новые.
2. В файле `catalog/controller/common/header.php` найти и заменить строку `https://widgets.saferoute.ru/cart/api.js`
на `https://widgets.saferoute.ru/cart/api.js?new`.
3. В настройках модуля в админке задать токен и ID магазина из Личного кабинета SafeRoute
(подробнее в инструкции по установке).
----


v2.0
----
Новая версия модуля для OpenCart 2.x взамен старой.
С нуля переписан весь код. Модуль встраивает свежую версию виджета SafeRoute.