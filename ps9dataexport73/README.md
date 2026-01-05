# ps9dataexport73 v1.3.1 (PS 1.7 / PHP 7.3)

Si te sale:
"Composer detected issues... require PHP >= 7.4"
casi siempre es porque hay un módulo viejo (o restos) con /vendor/ y su platform_check.php.

Este módulo NO usa Composer ni vendor y es compatible con PHP 7.3.

Pasos recomendados:
1) Por FTP, borra completamente:
   - /modules/ps9dataexport/
   - /modules/ps9dataexport73/  (si existe)
2) Borra caché:
   - /var/cache/*  (o /app/cache/* según tu PS)
3) Instala este ZIP desde el backoffice.
