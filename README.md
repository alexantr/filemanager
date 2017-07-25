# PHP File Manager

A good solution for managing files and folders on your site.

![PHP File Manager](https://raw.github.com/alexantr/filemanager/master/phpfm.png)

## How to use

Download ZIP with latest version from master branch.

Copy **filemanager.php** to your website folder and open it with web browser
(e.g. http://yoursite/any_path/filemanager.php).

Default username/password: **fm_admin**/**fm_admin**

*Warning:* Please set your own username and password in `$auth_users` before use. Password must be encripted with MD5.

To enable or disable authentication set `$use_auth` to `true` or `false`.

You can include file manager in another scripts. Just define `FM_EMBED` and other necessary constants. Example:

```php
class SomeController
{
    public function actionIndex()
    {
        define('FM_EMBED', true);
        define('FM_SELF_URL', UrlHelper::currentUrl()); // must be set if URL to manager not equal PHP_SELF
        require 'path/to/filemanager.php';
    }
}
```

Supported constants:

- `FM_ROOT_PATH` - default is `$_SERVER['DOCUMENT_ROOT']`
- `FM_ROOT_URL` - default is `'http(s)://site.domain/'`
- `FM_SELF_URL` - default is `'http(s)://site.domain/' . $_SERVER['PHP_SELF']`
- `FM_ICONV_INPUT_ENC` - default is `'CP1251'`
- `FM_USE_HIGHLIGHTJS` - default is `true`
- `FM_HIGHLIGHTJS_STYLE` - default is `'vs'`
- `FM_DATETIME_FORMAT` - default is `'d.m.y H:i'`

## Alternatives

- [Tiny PHP File Manager](https://github.com/prasathmani/tinyfilemanager) with search and file editor
- [simple php filemanager](https://github.com/jcampbell1/simple-file-manager)

## Bug tracker

If you have any issues with file manager, you may report them on
[Issue tracker](https://github.com/alexantr/filemanager/issues).

## License

This software is released under the MIT license.

Icons by [Yusuke Kamiyamane](http://p.yusukekamiyamane.com/).
