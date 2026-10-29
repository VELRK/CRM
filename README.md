# CRM

CodeIgniter serves this app. The front-end source stays in `frontend`. The server keeps CodeIgniter plus the built files in `public`.

Edit the front end here:

- `frontend/desk` is the React desk (`index.html`, `app.js`, `app.css`)
- `frontend/admin` is the PHP screen script and stylesheet
- `frontend/icons` are the icons

After a change, copy the same files onto the server:

```
php frontend/build.php
```

That writes `public/desk`, `public/js`, `public/css`, and `public/icons`. Those served files stay a copy of `frontend`.
