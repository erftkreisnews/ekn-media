# GZIP-Kompression (Serverseitig)

SEO-Tools wie seorch.de melden oft: „GZIP-Kompression fehlt“. Die Kompression wird **nicht von Laravel**, sondern vom **Webserver** (nginx/Apache) durchgeführt.

## Nginx

In der `server`- oder `http`-Konfiguration:

```nginx
gzip on;
gzip_vary on;
gzip_min_length 256;
gzip_types text/plain text/css application/json application/javascript application/x-javascript text/xml application/xml image/svg+xml;
```

Danach Nginx neu laden: `nginx -t && systemctl reload nginx`

## Apache (.htaccess im öffentlichen Ordner)

Falls du Apache mit mod_deflate nutzt, kann in `public/.htaccess` ergänzt werden:

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/css application/json application/javascript
</IfModule>
```

## Prüfen

Nach der Aktivierung: Response-Header der Seite prüfen. Es sollte `Content-Encoding: gzip` erscheinen (z. B. in den Browser-Entwicklertools unter Network → Headers).
