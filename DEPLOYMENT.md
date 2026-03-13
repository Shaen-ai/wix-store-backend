# Deployment Notes

## 413 Content Too Large when creating products

If you get **413 Content Too Large** when creating a product with images or uploading a GLB file, the web server is rejecting the request before it reaches the application. Increase the upload limits:

### PHP (upload_max_filesize, post_max_size)

- **PHP-FPM**: A `.user.ini` file is included in `public/`. Ensure your host allows `.user.ini` overrides. If not, set these in `php.ini`:
  - `upload_max_filesize = 100M`
  - `post_max_size = 105M`

- **Apache mod_php**: Add to `public/.htaccess` or server config:
  ```apache
  php_value upload_max_filesize 100M
  php_value post_max_size 105M
  ```

### Nginx

Add to your server block:

```nginx
client_max_body_size 105M;
```

See `nginx-upload.conf.example` for reference.

### Load balancers / reverse proxies

If you use Cloudflare, AWS ALB, or similar, ensure their body size limits allow at least 105MB.
