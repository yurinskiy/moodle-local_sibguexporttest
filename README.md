# Модуль "Создание билета ВИ"

Модуль располагается в блоке "Настройки" в разделе "Управление курсом", подразделе "Отчеты", пункте "Создание билета ВИ".
Модуль состоит из 4х вкладок:
- Скачать билеты ВИ;
- История заданий;
- Настройка билета ВИ;
- Генератор тестовых билетов.

Вкладка "Скачать билеты ВИ" выглядит как список пользователей курса с ролью студент и хотя бы одной законченной попыткой теста в курсе.
Вкладка "История заданий" содержит список выгрузок, который формировали пользователи на вкладке "Скачать билеты ВИ".
Вкладка "Настройка билета ВИ" содержит детальную настройку формата билета ВИ: колонтитулы, титульный лист, элементы курса включенные в билет ВИ.
Вкладка "Генератор тестовых билетов" содержит функционал для генерации тестовых билетов.

## Установка дополнительных библиотек
```shell
wget https://github.com/wkhtmltopdf/wkhtmltopdf/releases/download/0.12.4/wkhtmltox-0.12.4_linux-generic-amd64.tar.xz
tar vxf wkhtmltox-0.12.4_linux-generic-amd64.tar.xz
sudo cp wkhtmltox/bin/wk* /usr/local/bin/
sudo ln -s /usr/local/bin/wkhtmltopdf /usr/bin
sudo ln -s /usr/local/bin/wkhtmltoimage /usr/bin
wkhtmltopdf --version
```

## Для поддержки вопросов LaTeX
```shell
sudo apt install texlive texlive-latex-extra texlive-fonts-recommended pdf2svg
```
