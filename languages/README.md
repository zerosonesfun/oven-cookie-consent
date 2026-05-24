# Oven translations

- **oven-cookie-consent.pot** – Template with all translatable strings (source for translators).
- **oven-cookie-consent-en_US.po** / **.mo** – English (United States).
- **oven-cookie-consent-es_ES.po** / **.mo** – Spanish (Spain).
- **oven-cookie-consent-de_DE.po** / **.mo** – German (Germany).
- **oven-cookie-consent-it_IT.po** / **.mo** – Italian (Italy).
- **oven-cookie-consent-fr_FR.po** / **.mo** – French (France).
- **oven-cookie-consent-ru_RU.po** / **.mo** – Russian (Russia).
- **oven-cookie-consent-zh_CN.po** / **.mo** – Chinese (Simplified, China).
- **oven-cookie-consent-ja.po** / **.mo** – Japanese.

WordPress loads the compiled `.mo` files automatically when the plugin is installed from WordPress.org.

To update translations locally, edit the `.po` files and compile with gettext:

```bash
msgfmt -o oven-cookie-consent-en_US.mo oven-cookie-consent-en_US.po
```

Repeat for each locale file you change.
