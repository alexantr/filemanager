<?php
/**
 * PHP File Manager (2017-08-07)
 * https://github.com/alexantr/filemanager
 */

// Auth with login/password (set true/false to enable/disable it)
$use_auth = true;

// Users: array('Username' => 'Password', 'Username2' => 'Password2', ...)
$auth_users = array(
    'fm_admin' => 'fm_admin',
);

// Enable highlight.js (https://highlightjs.org/) on view's page
$use_highlightjs = true;

// highlight.js style
$highlightjs_style = 'vs';

// Default timezone for date() and time() - http://php.net/manual/en/timezones.php
$default_timezone = 'Europe/Minsk'; // UTC+3

// Root path for file manager
$root_path = $_SERVER['DOCUMENT_ROOT'];

// Root url for links in file manager.Relative to $http_host. Variants: '', 'path/to/subfolder'
// Will not working if $root_path will be outside of server document root
$root_url = '';

// Server hostname. Can set manually if wrong
$http_host = $_SERVER['HTTP_HOST'];

// input encoding for iconv
$iconv_input_encoding = 'CP1251';

// date() format for file modification date
$datetime_format = 'd.m.y H:i';

//--- EDIT BELOW CAREFULLY OR DO NOT EDIT AT ALL

// if fm included
if (defined('FM_EMBED')) {
    $use_auth = false;
} else {
    @set_time_limit(600);

    date_default_timezone_set($default_timezone);

    ini_set('default_charset', 'UTF-8');
    if (version_compare(PHP_VERSION, '5.6.0', '<') && function_exists('mb_internal_encoding')) {
        mb_internal_encoding('UTF-8');
    }
    if (function_exists('mb_regex_encoding')) {
        mb_regex_encoding('UTF-8');
    }

    session_cache_limiter('');
    session_name('filemanager');
    session_start();
}

if (empty($auth_users)) {
    $use_auth = false;
}

$is_https = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)
    || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https';

// clean and check $root_path
$root_path = rtrim($root_path, '\\/');
$root_path = str_replace('\\', '/', $root_path);
if (!@is_dir($root_path)) {
    echo sprintf('<h1>Root path "%s" not found!</h1>', fm_enc($root_path));
    exit;
}

// clean $root_url
$root_url = fm_clean_path($root_url);

// abs path for site
defined('FM_ROOT_PATH') || define('FM_ROOT_PATH', $root_path);
defined('FM_ROOT_URL') || define('FM_ROOT_URL', ($is_https ? 'https' : 'http') . '://' . $http_host . (!empty($root_url) ? '/' . $root_url : ''));
defined('FM_SELF_URL') || define('FM_SELF_URL', ($is_https ? 'https' : 'http') . '://' . $http_host . $_SERVER['PHP_SELF']);

// logout
if (isset($_GET['logout'])) {
    unset($_SESSION['logged']);
    fm_redirect(FM_SELF_URL);
}

// Show image here
if (isset($_GET['img'])) {
    fm_show_image($_GET['img']);
}

// Auth
if ($use_auth) {
    if (isset($_SESSION['logged'], $auth_users[$_SESSION['logged']])) {
        // Logged
    } elseif (isset($_POST['fm_usr'], $_POST['fm_pwd'])) {
        // Logging In
        sleep(1);
        if (isset($auth_users[$_POST['fm_usr']]) && $_POST['fm_pwd'] === $auth_users[$_POST['fm_usr']]) {
            $_SESSION['logged'] = $_POST['fm_usr'];
            fm_set_msg('You are logged in');
            fm_redirect(FM_SELF_URL . '?p=');
        } else {
            unset($_SESSION['logged']);
            fm_set_msg('Wrong password', 'error');
            fm_redirect(FM_SELF_URL);
        }
    } else {
        // Form
        unset($_SESSION['logged']);
        fm_show_header();
        fm_show_message();
        ?>
        <div class="path">
            <form action="" method="post" style="margin:10px;text-align:center">
                <input name="fm_usr" value="" placeholder="Username" required>
                <input type="password" name="fm_pwd" value="" placeholder="Password" required>
                <input type="submit" value="Login">
            </form>
        </div>
        <?php
        fm_show_footer();
        exit;
    }
}

define('FM_IS_WIN', DIRECTORY_SEPARATOR == '\\');

// always use ?p=
if (!isset($_GET['p'])) {
    fm_redirect(FM_SELF_URL . '?p=');
}

// get path
$p = isset($_GET['p']) ? $_GET['p'] : (isset($_POST['p']) ? $_POST['p'] : '');

// clean path
$p = fm_clean_path($p);

// instead globals vars
define('FM_PATH', $p);
define('FM_USE_AUTH', $use_auth);

defined('FM_ICONV_INPUT_ENC') || define('FM_ICONV_INPUT_ENC', $iconv_input_encoding);
defined('FM_USE_HIGHLIGHTJS') || define('FM_USE_HIGHLIGHTJS', $use_highlightjs);
defined('FM_HIGHLIGHTJS_STYLE') || define('FM_HIGHLIGHTJS_STYLE', $highlightjs_style);
defined('FM_DATETIME_FORMAT') || define('FM_DATETIME_FORMAT', $datetime_format);

unset($p, $use_auth, $iconv_input_encoding, $use_highlightjs, $highlightjs_style);

/*************************** ACTIONS ***************************/

// Delete file / folder
if (isset($_GET['del'])) {
    $del = $_GET['del'];
    $del = fm_clean_path($del);
    $del = str_replace('/', '', $del);
    if ($del != '' && $del != '..' && $del != '.') {
        $path = FM_ROOT_PATH;
        if (FM_PATH != '') {
            $path .= '/' . FM_PATH;
        }
        $is_dir = is_dir($path . '/' . $del);
        if (fm_rdelete($path . '/' . $del)) {
            $msg = $is_dir ? 'Folder <b>%s</b> deleted' : 'File <b>%s</b> deleted';
            fm_set_msg(sprintf($msg, fm_enc($del)));
        } else {
            $msg = $is_dir ? 'Folder <b>%s</b> not deleted' : 'File <b>%s</b> not deleted';
            fm_set_msg(sprintf($msg, fm_enc($del)), 'error');
        }
    } else {
        fm_set_msg('Wrong file or folder name', 'error');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Create folder
if (isset($_GET['new'])) {
    $new = strip_tags($_GET['new']); // remove unwanted characters from folder name
    $new = fm_clean_path($new);
    $new = str_replace('/', '', $new);
    if ($new != '' && $new != '..' && $new != '.') {
        $path = FM_ROOT_PATH;
        if (FM_PATH != '') {
            $path .= '/' . FM_PATH;
        }
        if (fm_mkdir($path . '/' . $new, false) === true) {
            fm_set_msg(sprintf('Folder <b>%s</b> created', fm_enc($new)));
        } elseif (fm_mkdir($path . '/' . $new, false) === $path . '/' . $new) {
            fm_set_msg(sprintf('Folder <b>%s</b> already exists', fm_enc($new)), 'alert');
        } else {
            fm_set_msg(sprintf('Folder <b>%s</b> not created', fm_enc($new)), 'error');
        }
    } else {
        fm_set_msg('Wrong folder name', 'error');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Copy folder / file
if (isset($_GET['copy'], $_GET['finish'])) {
    // from
    $copy = $_GET['copy'];
    $copy = fm_clean_path($copy);
    // empty path
    if ($copy == '') {
        fm_set_msg('Source path not defined', 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    // abs path from
    $from = FM_ROOT_PATH . '/' . $copy;
    // abs path to
    $dest = FM_ROOT_PATH;
    if (FM_PATH != '') {
        $dest .= '/' . FM_PATH;
    }
    $dest .= '/' . basename($from);
    // move?
    $move = isset($_GET['move']);
    // copy/move
    if ($from != $dest) {
        $msg_from = trim(FM_PATH . '/' . basename($from), '/');
        if ($move) {
            $rename = fm_rename($from, $dest);
            if ($rename) {
                fm_set_msg(sprintf('Moved from <b>%s</b> to <b>%s</b>', fm_enc($copy), fm_enc($msg_from)));
            } elseif ($rename === null) {
                fm_set_msg('File or folder with this path already exists', 'alert');
            } else {
                fm_set_msg(sprintf('Error while moving from <b>%s</b> to <b>%s</b>', fm_enc($copy), fm_enc($msg_from)), 'error');
            }
        } else {
            if (fm_rcopy($from, $dest)) {
                fm_set_msg(sprintf('Copyied from <b>%s</b> to <b>%s</b>', fm_enc($copy), fm_enc($msg_from)));
            } else {
                fm_set_msg(sprintf('Error while copying from <b>%s</b> to <b>%s</b>', fm_enc($copy), fm_enc($msg_from)), 'error');
            }
        }
    } else {
        fm_set_msg('Paths must be not equal', 'alert');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Mass copy files/ folders
if (isset($_POST['file'], $_POST['copy_to'], $_POST['finish'])) {
    // from
    $path = FM_ROOT_PATH;
    if (FM_PATH != '') {
        $path .= '/' . FM_PATH;
    }
    // to
    $copy_to_path = FM_ROOT_PATH;
    $copy_to = fm_clean_path($_POST['copy_to']);
    if ($copy_to != '') {
        $copy_to_path .= '/' . $copy_to;
    }
    if ($path == $copy_to_path) {
        fm_set_msg('Paths must be not equal', 'alert');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
    if (!is_dir($copy_to_path)) {
        if (!fm_mkdir($copy_to_path, true)) {
            fm_set_msg('Unable to create destination folder', 'error');
            fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
        }
    }
    // move?
    $move = isset($_POST['move']);
    // copy/move
    $errors = 0;
    $files = $_POST['file'];
    if (is_array($files) && count($files)) {
        foreach ($files as $f) {
            if ($f != '') {
                // abs path from
                $from = $path . '/' . $f;
                // abs path to
                $dest = $copy_to_path . '/' . $f;
                // do
                if ($move) {
                    $rename = fm_rename($from, $dest);
                    if ($rename === false) {
                        $errors++;
                    }
                } else {
                    if (!fm_rcopy($from, $dest)) {
                        $errors++;
                    }
                }
            }
        }
        if ($errors == 0) {
            $msg = $move ? 'Selected files and folders moved' : 'Selected files and folders copied';
            fm_set_msg($msg);
        } else {
            $msg = $move ? 'Error while moving items' : 'Error while copying items';
            fm_set_msg($msg, 'error');
        }
    } else {
        fm_set_msg('Nothing selected', 'alert');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Rename
if (isset($_GET['ren'], $_GET['to'])) {
    // old name
    $old = $_GET['ren'];
    $old = fm_clean_path($old);
    $old = str_replace('/', '', $old);
    // new name
    $new = $_GET['to'];
    $new = fm_clean_path($new);
    $new = str_replace('/', '', $new);
    // path
    $path = FM_ROOT_PATH;
    if (FM_PATH != '') {
        $path .= '/' . FM_PATH;
    }
    // rename
    if ($old != '' && $new != '') {
        if (fm_rename($path . '/' . $old, $path . '/' . $new)) {
            fm_set_msg(sprintf('Renamed from <b>%s</b> to <b>%s</b>', fm_enc($old), fm_enc($new)));
        } else {
            fm_set_msg(sprintf('Error while renaming from <b>%s</b> to <b>%s</b>', fm_enc($old), fm_enc($new)), 'error');
        }
    } else {
        fm_set_msg('Names not set', 'error');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Download
if (isset($_GET['dl'])) {
    $dl = $_GET['dl'];
    $dl = fm_clean_path($dl);
    $dl = str_replace('/', '', $dl);
    $path = FM_ROOT_PATH;
    if (FM_PATH != '') {
        $path .= '/' . FM_PATH;
    }
    if ($dl != '' && is_file($path . '/' . $dl)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($path . '/' . $dl) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($path . '/' . $dl));
        readfile($path . '/' . $dl);
        exit;
    } else {
        fm_set_msg('File not found', 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }
}

// Upload
if (isset($_POST['upl'])) {
    $path = FM_ROOT_PATH;
    if (FM_PATH != '') {
        $path .= '/' . FM_PATH;
    }

    $errors = 0;
    $uploads = 0;
    $total = count($_FILES['upload']['name']);

    for ($i = 0; $i < $total; $i++) {
        $tmp_name = $_FILES['upload']['tmp_name'][$i];
        if (empty($_FILES['upload']['error'][$i]) && !empty($tmp_name) && $tmp_name != 'none') {
            if (move_uploaded_file($tmp_name, $path . '/' . $_FILES['upload']['name'][$i])) {
                $uploads++;
            } else {
                $errors++;
            }
        }
    }

    if ($errors == 0 && $uploads > 0) {
        fm_set_msg(sprintf('All files uploaded to <b>%s</b>', fm_enc($path)));
    } elseif ($errors == 0 && $uploads == 0) {
        fm_set_msg('Nothing uploaded', 'alert');
    } else {
        fm_set_msg(sprintf('Error while uploading files. Uploaded files: %s', $uploads), 'error');
    }

    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Mass deleting
if (isset($_POST['group'], $_POST['delete'])) {
    $path = FM_ROOT_PATH;
    if (FM_PATH != '') {
        $path .= '/' . FM_PATH;
    }

    $errors = 0;
    $files = $_POST['file'];
    if (is_array($files) && count($files)) {
        foreach ($files as $f) {
            if ($f != '') {
                $new_path = $path . '/' . $f;
                if (!fm_rdelete($new_path)) {
                    $errors++;
                }
            }
        }
        if ($errors == 0) {
            fm_set_msg('Selected files and folder deleted');
        } else {
            fm_set_msg('Error while deleting items', 'error');
        }
    } else {
        fm_set_msg('Nothing selected', 'alert');
    }

    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Pack files
if (isset($_POST['group'], $_POST['zip'])) {
    $path = FM_ROOT_PATH;
    if (FM_PATH != '') {
        $path .= '/' . FM_PATH;
    }

    if (!class_exists('ZipArchive')) {
        fm_set_msg('Operations with archives are not available', 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }

    $files = $_POST['file'];
    if (!empty($files)) {
        chdir($path);

        if (count($files) == 1) {
            $one_file = reset($files);
            $one_file = basename($one_file);
            $zipname = $one_file . '_' . date('ymd_His') . '.zip';
        } else {
            $zipname = 'archive_' . date('ymd_His') . '.zip';
        }

        $zipper = new FM_Zipper();
        $res = $zipper->create($zipname, $files);

        if ($res) {
            fm_set_msg(sprintf('Archive <b>%s</b> created', fm_enc($zipname)));
        } else {
            fm_set_msg('Archive not created', 'error');
        }
    } else {
        fm_set_msg('Nothing selected', 'alert');
    }

    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Unpack
if (isset($_GET['unzip'])) {
    $unzip = $_GET['unzip'];
    $unzip = fm_clean_path($unzip);
    $unzip = str_replace('/', '', $unzip);

    $path = FM_ROOT_PATH;
    if (FM_PATH != '') {
        $path .= '/' . FM_PATH;
    }

    if (!class_exists('ZipArchive')) {
        fm_set_msg('Operations with archives are not available', 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }

    if ($unzip != '' && is_file($path . '/' . $unzip)) {
        $zip_path = $path . '/' . $unzip;

        //to folder
        $tofolder = '';
        if (isset($_GET['tofolder'])) {
            $tofolder = pathinfo($zip_path, PATHINFO_FILENAME);
            if (fm_mkdir($path . '/' . $tofolder, true)) {
                $path .= '/' . $tofolder;
            }
        }

        $zipper = new FM_Zipper();
        $res = $zipper->unzip($zip_path, $path);

        if ($res) {
            fm_set_msg('Archive unpacked');
        } else {
            fm_set_msg('Archive not unpacked', 'error');
        }

    } else {
        fm_set_msg('File not found', 'error');
    }
    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

// Change Perms (not for Windows)
if (isset($_POST['chmod']) && !FM_IS_WIN) {
    $path = FM_ROOT_PATH;
    if (FM_PATH != '') {
        $path .= '/' . FM_PATH;
    }

    $file = $_POST['chmod'];
    $file = fm_clean_path($file);
    $file = str_replace('/', '', $file);
    if ($file == '' || (!is_file($path . '/' . $file) && !is_dir($path . '/' . $file))) {
        fm_set_msg('File not found', 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }

    $mode = 0;
    if (!empty($_POST['ur'])) {
        $mode |= 0400;
    }
    if (!empty($_POST['uw'])) {
        $mode |= 0200;
    }
    if (!empty($_POST['ux'])) {
        $mode |= 0100;
    }
    if (!empty($_POST['gr'])) {
        $mode |= 0040;
    }
    if (!empty($_POST['gw'])) {
        $mode |= 0020;
    }
    if (!empty($_POST['gx'])) {
        $mode |= 0010;
    }
    if (!empty($_POST['or'])) {
        $mode |= 0004;
    }
    if (!empty($_POST['ow'])) {
        $mode |= 0002;
    }
    if (!empty($_POST['ox'])) {
        $mode |= 0001;
    }

    if (@chmod($path . '/' . $file, $mode)) {
        fm_set_msg('Permissions changed');
    } else {
        fm_set_msg('Permissions not changed', 'error');
    }

    fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
}

/*************************** /ACTIONS ***************************/

// get current path
$path = FM_ROOT_PATH;
if (FM_PATH != '') {
    $path .= '/' . FM_PATH;
}

// check path
if (!is_dir($path)) {
    fm_redirect(FM_SELF_URL . '?p=');
}

// get parent folder
$parent = fm_get_parent_path(FM_PATH);

$objects = is_readable($path) ? scandir($path) : array();
$folders = array();
$files = array();
if (is_array($objects)) {
    foreach ($objects as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        $new_path = $path . '/' . $file;
        if (is_file($new_path)) {
            $files[] = $file;
        } elseif (is_dir($new_path) && $file != '.' && $file != '..') {
            $folders[] = $file;
        }
    }
}

if (!empty($files)) {
    natcasesort($files);
}
if (!empty($folders)) {
    natcasesort($folders);
}

// upload form
if (isset($_GET['upload'])) {
    fm_show_header(); // HEADER
    fm_show_nav_path(FM_PATH); // current path
    ?>
    <div class="path">
        <p><b>Uploading files</b></p>
        <p class="break-word">Destination folder: <?php echo fm_enc(fm_convert_win(FM_ROOT_PATH . '/' . FM_PATH)) ?></p>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="hidden" name="p" value="<?php echo fm_enc(FM_PATH) ?>">
            <input type="hidden" name="upl" value="1">
            <input type="file" name="upload[]"><br>
            <input type="file" name="upload[]"><br>
            <input type="file" name="upload[]"><br>
            <input type="file" name="upload[]"><br>
            <input type="file" name="upload[]"><br>
            <br>
            <p>
                <button class="btn"><i class="icon-apply"></i> Upload</button> &nbsp;
                <b><a href="?p=<?php echo urlencode(FM_PATH) ?>"><i class="icon-cancel"></i> Cancel</a></b>
            </p>
        </form>
    </div>
    <?php
    fm_show_footer();
    exit;
}

// copy form POST
if (isset($_POST['copy'])) {
    $copy_files = $_POST['file'];
    if (!is_array($copy_files) || empty($copy_files)) {
        fm_set_msg('Nothing selected', 'alert');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }

    fm_show_header(); // HEADER
    fm_show_nav_path(FM_PATH); // current path
    ?>
    <div class="path">
        <p><b>Copying</b></p>
        <form action="" method="post">
            <input type="hidden" name="p" value="<?php echo fm_enc(FM_PATH) ?>">
            <input type="hidden" name="finish" value="1">
            <?php
            foreach ($copy_files as $cf) {
                echo '<input type="hidden" name="file[]" value="' . fm_enc($cf) . '">' . PHP_EOL;
            }
            $copy_files_enc = array_map('fm_enc', $copy_files);
            ?>
            <p class="break-word">Files: <b><?php echo implode('</b>, <b>', $copy_files_enc) ?></b></p>
            <p class="break-word">Source folder: <?php echo fm_enc(fm_convert_win(FM_ROOT_PATH . '/' . FM_PATH)) ?><br>
                <label for="inp_copy_to">Destination folder:</label>
                <?php echo FM_ROOT_PATH ?>/<input name="copy_to" id="inp_copy_to" value="<?php echo fm_enc(FM_PATH) ?>">
            </p>
            <p><label><input type="checkbox" name="move" value="1"> Move</label></p>
            <p>
                <button class="btn"><i class="icon-apply"></i> Copy</button> &nbsp;
                <b><a href="?p=<?php echo urlencode(FM_PATH) ?>"><i class="icon-cancel"></i> Cancel</a></b>
            </p>
        </form>
    </div>
    <?php
    fm_show_footer();
    exit;
}

// copy form
if (isset($_GET['copy']) && !isset($_GET['finish'])) {
    $copy = $_GET['copy'];
    $copy = fm_clean_path($copy);
    if ($copy == '' || !file_exists(FM_ROOT_PATH . '/' . $copy)) {
        fm_set_msg('File not found', 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }

    fm_show_header(); // HEADER
    fm_show_nav_path(FM_PATH); // current path
    ?>
    <div class="path">
        <p><b>Copying</b></p>
        <p class="break-word">
            Source path: <?php echo fm_enc(fm_convert_win(FM_ROOT_PATH . '/' . $copy)) ?><br>
            Destination folder: <?php echo fm_enc(fm_convert_win(FM_ROOT_PATH . '/' . FM_PATH)) ?>
        </p>
        <p>
            <b><a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;copy=<?php echo urlencode($copy) ?>&amp;finish=1"><i class="icon-apply"></i> Copy</a></b> &nbsp;
            <b><a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;copy=<?php echo urlencode($copy) ?>&amp;finish=1&amp;move=1"><i class="icon-apply"></i> Move</a></b> &nbsp;
            <b><a href="?p=<?php echo urlencode(FM_PATH) ?>"><i class="icon-cancel"></i> Cancel</a></b>
        </p>
        <p><i>Select folder:</i></p>
        <ul class="folders break-word">
            <?php
            if ($parent !== false) {
                ?>
                <li><a href="?p=<?php echo urlencode($parent) ?>&amp;copy=<?php echo urlencode($copy) ?>"><i class="icon-arrow_up"></i> ..</a></li>
            <?php
            }
            foreach ($folders as $f) {
                ?>
                <li><a href="?p=<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) ?>&amp;copy=<?php echo urlencode($copy) ?>"><i class="icon-folder"></i> <?php echo fm_enc(fm_convert_win($f)) ?></a></li>
            <?php
            }
            ?>
        </ul>
    </div>
    <?php
    fm_show_footer();
    exit;
}

// file viewer
if (isset($_GET['view'])) {
    $file = $_GET['view'];
    $file = fm_clean_path($file);
    $file = str_replace('/', '', $file);
    if ($file == '' || !is_file($path . '/' . $file)) {
        fm_set_msg('File not found', 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }

    fm_show_header(); // HEADER
    fm_show_nav_path(FM_PATH); // current path

    $file_url = FM_ROOT_URL . fm_convert_win((FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $file);
    $file_path = $path . '/' . $file;

    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $mime_type = fm_get_mime_type($file_path);
    $filesize = filesize($file_path);

    $is_zip = false;
    $is_image = false;
    $is_audio = false;
    $is_video = false;
    $is_text = false;

    $view_title = 'File';
    $filenames = false; // for zip
    $content = ''; // for text

    if ($ext == 'zip') {
        $is_zip = true;
        $view_title = 'Archive';
        $filenames = fm_get_zif_info($file_path);
    } elseif (in_array($ext, fm_get_image_exts())) {
        $is_image = true;
        $view_title = 'Image';
    } elseif (in_array($ext, fm_get_audio_exts())) {
        $is_audio = true;
        $view_title = 'Audio';
    } elseif (in_array($ext, fm_get_video_exts())) {
        $is_video = true;
        $view_title = 'Video';
    } elseif (in_array($ext, fm_get_text_exts()) || substr($mime_type, 0, 4) == 'text' || in_array($mime_type, fm_get_text_mimes())) {
        $is_text = true;
        $content = file_get_contents($file_path);
    }

    ?>
    <div class="path">
        <p class="break-word"><b><?php echo $view_title ?> "<?php echo fm_enc(fm_convert_win($file)) ?>"</b></p>
        <p class="break-word">
            Full path: <?php echo fm_enc(fm_convert_win($file_path)) ?><br>
            File size: <?php echo fm_get_filesize($filesize) ?><?php if ($filesize >= 1000): ?> (<?php echo sprintf('%s bytes', $filesize) ?>)<?php endif; ?><br>
            MIME-type: <?php echo $mime_type ?><br>
            <?php
            // ZIP info
            if ($is_zip && $filenames !== false) {
                $total_files = 0;
                $total_comp = 0;
                $total_uncomp = 0;
                foreach ($filenames as $fn) {
                    if (!$fn['folder']) {
                        $total_files++;
                    }
                    $total_comp += $fn['compressed_size'];
                    $total_uncomp += $fn['filesize'];
                }
                ?>
                Files in archive: <?php echo $total_files ?><br>
                Total size: <?php echo fm_get_filesize($total_uncomp) ?><br>
                Size in archive: <?php echo fm_get_filesize($total_comp) ?><br>
                Compression: <?php echo round(($total_comp / $total_uncomp) * 100) ?>%<br>
                <?php
            }
            // Image info
            if ($is_image) {
                $image_size = getimagesize($file_path);
                echo 'Image sizes: ' . (isset($image_size[0]) ? $image_size[0] : '0') . ' x ' . (isset($image_size[1]) ? $image_size[1] : '0') . '<br>';
            }
            // Text info
            if ($is_text) {
                $is_utf8 = fm_is_utf8($content);
                if (function_exists('iconv')) {
                    if (!$is_utf8) {
                        $content = iconv(FM_ICONV_INPUT_ENC, 'UTF-8//IGNORE', $content);
                    }
                }
                echo 'Charset: ' . ($is_utf8 ? 'utf-8' : '8 bit') . '<br>';
            }
            ?>
        </p>
        <p>
            <b><a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;dl=<?php echo urlencode($file) ?>"><i class="icon-download"></i> Download</a></b> &nbsp;
            <b><a href="<?php echo fm_enc($file_url) ?>" target="_blank"><i class="icon-chain"></i> Open</a></b> &nbsp;
            <?php
            // ZIP actions
            if ($is_zip && $filenames !== false) {
                $zip_name = pathinfo($file_path, PATHINFO_FILENAME);
                ?>
                <b><a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;unzip=<?php echo urlencode($file) ?>"><i class="icon-apply"></i> Unpack</a></b> &nbsp;
                <b><a href="?p=<?php echo urlencode(FM_PATH) ?>&amp;unzip=<?php echo urlencode($file) ?>&amp;tofolder=1" title="Unpack to <?php echo fm_enc($zip_name) ?>"><i class="icon-apply"></i>
                    Unpack to folder</a></b> &nbsp;
                <?php
            }
            ?>
            <b><a href="?p=<?php echo urlencode(FM_PATH) ?>"><i class="icon-goback"></i> Back</a></b>
        </p>
        <?php
        if ($is_zip) {
            // ZIP content
            if ($filenames !== false) {
                echo '<code class="maxheight">';
                foreach ($filenames as $fn) {
                    if ($fn['folder']) {
                        echo '<b>' . fm_enc($fn['name']) . '</b><br>';
                    } else {
                        echo $fn['name'] . ' (' . fm_get_filesize($fn['filesize']) . ')<br>';
                    }
                }
                echo '</code>';
            } else {
                echo '<p>Error while fetching archive info</p>';
            }
        } elseif ($is_image) {
            // Image content
            if (in_array($ext, array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico'))) {
                echo '<p><img src="' . fm_enc($file_url) . '" alt="" class="preview-img"></p>';
            }
        } elseif ($is_audio) {
            // Audio content
            echo '<p><audio src="' . fm_enc($file_url) . '" controls preload="metadata"></audio></p>';
        } elseif ($is_video) {
            // Video content
            echo '<div class="preview-video"><video src="' . fm_enc($file_url) . '" width="640" height="360" controls preload="metadata"></video></div>';
        } elseif ($is_text) {
            if (FM_USE_HIGHLIGHTJS) {
                // highlight
                $hljs_classes = array(
                    'shtml' => 'xml',
                    'htaccess' => 'apache',
                    'phtml' => 'php',
                    'lock' => 'json',
                    'svg' => 'xml',
                );
                $hljs_class = isset($hljs_classes[$ext]) ? 'lang-' . $hljs_classes[$ext] : 'lang-' . $ext;
                if (empty($ext) || in_array(strtolower($file), fm_get_text_names()) || preg_match('#\.min\.(css|js)$#i', $file)) {
                    $hljs_class = 'nohighlight';
                }
                $content = '<pre class="with-hljs"><code class="' . $hljs_class . '">' . fm_enc($content) . '</code></pre>';
            } elseif (in_array($ext, array('php', 'php4', 'php5', 'phtml', 'phps'))) {
                // php highlight
                $content = highlight_string($content, true);
            } else {
                $content = '<pre>' . fm_enc($content) . '</pre>';
            }
            echo $content;
        }
        ?>
    </div>
    <?php
    fm_show_footer();
    exit;
}

// chmod (not for Windows)
if (isset($_GET['chmod']) && !FM_IS_WIN) {
    $file = $_GET['chmod'];
    $file = fm_clean_path($file);
    $file = str_replace('/', '', $file);
    if ($file == '' || (!is_file($path . '/' . $file) && !is_dir($path . '/' . $file))) {
        fm_set_msg('File not found', 'error');
        fm_redirect(FM_SELF_URL . '?p=' . urlencode(FM_PATH));
    }

    fm_show_header(); // HEADER
    fm_show_nav_path(FM_PATH); // current path

    $file_url = FM_ROOT_URL . (FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $file;
    $file_path = $path . '/' . $file;

    $mode = fileperms($path . '/' . $file);

    ?>
    <div class="path">
        <p><b>Change Permissions</b></p>
        <p>
            Full path: <?php echo fm_enc($file_path) ?><br>
        </p>
        <form action="" method="post">
            <input type="hidden" name="p" value="<?php echo fm_enc(FM_PATH) ?>">
            <input type="hidden" name="chmod" value="<?php echo fm_enc($file) ?>">

            <table class="compact-table">
                <tr>
                    <td></td>
                    <td><b>Owner</b></td>
                    <td><b>Group</b></td>
                    <td><b>Other</b></td>
                </tr>
                <tr>
                    <td style="text-align: right"><b>Read</b></td>
                    <td><label><input type="checkbox" name="ur" value="1"<?php echo ($mode & 00400) ? ' checked' : '' ?>></label></td>
                    <td><label><input type="checkbox" name="gr" value="1"<?php echo ($mode & 00040) ? ' checked' : '' ?>></label></td>
                    <td><label><input type="checkbox" name="or" value="1"<?php echo ($mode & 00004) ? ' checked' : '' ?>></label></td>
                </tr>
                <tr>
                    <td style="text-align: right"><b>Write</b></td>
                    <td><label><input type="checkbox" name="uw" value="1"<?php echo ($mode & 00200) ? ' checked' : '' ?>></label></td>
                    <td><label><input type="checkbox" name="gw" value="1"<?php echo ($mode & 00020) ? ' checked' : '' ?>></label></td>
                    <td><label><input type="checkbox" name="ow" value="1"<?php echo ($mode & 00002) ? ' checked' : '' ?>></label></td>
                </tr>
                <tr>
                    <td style="text-align: right"><b>Execute</b></td>
                    <td><label><input type="checkbox" name="ux" value="1"<?php echo ($mode & 00100) ? ' checked' : '' ?>></label></td>
                    <td><label><input type="checkbox" name="gx" value="1"<?php echo ($mode & 00010) ? ' checked' : '' ?>></label></td>
                    <td><label><input type="checkbox" name="ox" value="1"<?php echo ($mode & 00001) ? ' checked' : '' ?>></label></td>
                </tr>
            </table>

            <p>
                <button class="btn"><i class="icon-apply"></i> Change</button> &nbsp;
                <b><a href="?p=<?php echo urlencode(FM_PATH) ?>"><i class="icon-cancel"></i> Cancel</a></b>
            </p>

        </form>

    </div>
    <?php
    fm_show_footer();
    exit;
}

//--- FILEMANAGER MAIN
fm_show_header(); // HEADER
fm_show_nav_path(FM_PATH); // current path

// messages
fm_show_message();

$num_files = count($files);
$num_folders = count($folders);
$all_files_size = 0;
?>
<form action="" method="post">
<input type="hidden" name="p" value="<?php echo fm_enc(FM_PATH) ?>">
<input type="hidden" name="group" value="1">
<table><tr>
<th style="width:3%"><label><input type="checkbox" title="Invert selection" onclick="checkbox_toggle()"></label></th>
<th>Name</th><th style="width:10%">Size</th>
<th style="width:12%">Modified</th>
<?php if (!FM_IS_WIN): ?><th style="width:6%">Perms</th><th style="width:10%">Owner</th><?php endif; ?>
<th style="width:13%"></th></tr>
<?php
// link to parent folder
if ($parent !== false) {
    ?>
<tr><td></td><td colspan="<?php echo !FM_IS_WIN ? '6' : '4' ?>"><a href="?p=<?php echo urlencode($parent) ?>"><i class="icon-arrow_up"></i> ..</a></td></tr>
<?php
}
foreach ($folders as $f) {
    $is_link = is_link($path . '/' . $f);
    $img = $is_link ? 'icon-link_folder' : 'icon-folder';
    $modif = date(FM_DATETIME_FORMAT, filemtime($path . '/' . $f));
    $perms = substr(decoct(fileperms($path . '/' . $f)), -4);
    if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) {
        $owner = posix_getpwuid(fileowner($path . '/' . $f));
        $group = posix_getgrgid(filegroup($path . '/' . $f));
    } else {
        $owner = array('name' => '?');
        $group = array('name' => '?');
    }
    ?>
<tr>
<td><label><input type="checkbox" name="file[]" value="<?php echo fm_enc($f) ?>"></label></td>
<td><div class="filename"><a href="?p=<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) ?>"><i class="<?php echo $img ?>"></i> <?php echo fm_enc(fm_convert_win($f)) ?></a><?php echo ($is_link ? ' &rarr; <i>' . fm_enc(readlink($path . '/' . $f)) . '</i>' : '') ?></div></td>
<td>Folder</td><td><?php echo $modif ?></td>
<?php if (!FM_IS_WIN): ?>
<td><a title="Change Permissions" href="?p=<?php echo urlencode(FM_PATH) ?>&amp;chmod=<?php echo urlencode($f) ?>"><?php echo $perms ?></a></td>
<td><?php echo fm_enc($owner['name'] . ':' . $group['name']) ?></td>
<?php endif; ?>
<td>
<a title="Delete" href="?p=<?php echo urlencode(FM_PATH) ?>&amp;del=<?php echo urlencode($f) ?>" onclick="return confirm('Delete folder?');"><i class="icon-cross"></i></a>
<a title="Rename" href="#" onclick="rename('<?php echo fm_enc(FM_PATH) ?>', '<?php echo fm_enc($f) ?>');return false;"><i class="icon-rename"></i></a>
<a title="Copy to..." href="?p=&amp;copy=<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) ?>"><i class="icon-copy"></i></a>
<a title="Direct link" href="<?php echo fm_enc(FM_ROOT_URL . (FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $f . '/') ?>" target="_blank"><i class="icon-chain"></i></a>
</td></tr>
    <?php
    flush();
}

foreach ($files as $f) {
    $is_link = is_link($path . '/' . $f);
    $img = $is_link ? 'icon-link_file' : fm_get_file_icon_class($path . '/' . $f);
    $modif = date(FM_DATETIME_FORMAT, filemtime($path . '/' . $f));
    $filesize_raw = filesize($path . '/' . $f);
    $filesize = fm_get_filesize($filesize_raw);
    $filelink = '?p=' . urlencode(FM_PATH) . '&view=' . urlencode($f);
    $all_files_size += $filesize_raw;
    $perms = substr(decoct(fileperms($path . '/' . $f)), -4);
    if (function_exists('posix_getpwuid') && function_exists('posix_getgrgid')) {
        $owner = posix_getpwuid(fileowner($path . '/' . $f));
        $group = posix_getgrgid(filegroup($path . '/' . $f));
    } else {
        $owner = array('name' => '?');
        $group = array('name' => '?');
    }
    ?>
<tr>
<td><label><input type="checkbox" name="file[]" value="<?php echo fm_enc($f) ?>"></label></td>
<td><div class="filename"><a href="<?php echo fm_enc($filelink) ?>" title="File info"><i class="<?php echo $img ?>"></i> <?php echo fm_enc(fm_convert_win($f)) ?></a><?php echo ($is_link ? ' &rarr; <i>' . fm_enc(readlink($path . '/' . $f)) . '</i>' : '') ?></div></td>
<td><span class="gray" title="<?php printf('%s bytes', $filesize_raw) ?>"><?php echo $filesize ?></span></td>
<td><?php echo $modif ?></td>
<?php if (!FM_IS_WIN): ?>
<td><a title="Change Permissions" href="?p=<?php echo urlencode(FM_PATH) ?>&amp;chmod=<?php echo urlencode($f) ?>"><?php echo $perms ?></a></td>
<td><?php echo fm_enc($owner['name'] . ':' . $group['name']) ?></td>
<?php endif; ?>
<td>
<a title="Delete" href="?p=<?php echo urlencode(FM_PATH) ?>&amp;del=<?php echo urlencode($f) ?>" onclick="return confirm('Delete file?');"><i class="icon-cross"></i></a>
<a title="Rename" href="#" onclick="rename('<?php echo fm_enc(FM_PATH) ?>', '<?php echo fm_enc($f) ?>');return false;"><i class="icon-rename"></i></a>
<a title="Copy to..." href="?p=<?php echo urlencode(FM_PATH) ?>&amp;copy=<?php echo urlencode(trim(FM_PATH . '/' . $f, '/')) ?>"><i class="icon-copy"></i></a>
<a title="Direct link" href="<?php echo fm_enc(FM_ROOT_URL . (FM_PATH != '' ? '/' . FM_PATH : '') . '/' . $f) ?>" target="_blank"><i class="icon-chain"></i></a>
<a title="Download" href="?p=<?php echo urlencode(FM_PATH) ?>&amp;dl=<?php echo urlencode($f) ?>"><i class="icon-download"></i></a>
</td></tr>
    <?php
    flush();
}

if (empty($folders) && empty($files)) {
    ?>
<tr><td></td><td colspan="<?php echo !FM_IS_WIN ? '6' : '4' ?>"><em>Folder is empty</em></td></tr>
<?php
} else {
    ?>
<tr><td class="gray"></td><td class="gray" colspan="<?php echo !FM_IS_WIN ? '6' : '4' ?>">
Full size: <span title="<?php printf('%s bytes', $all_files_size) ?>"><?php echo fm_get_filesize($all_files_size) ?></span>,
files: <?php echo $num_files ?>,
folders: <?php echo $num_folders ?>
</td></tr>
<?php
}
?>
</table>
<p class="path"><a href="#" onclick="select_all();return false;"><i class="icon-checkbox"></i> Select all</a> &nbsp;
<a href="#" onclick="unselect_all();return false;"><i class="icon-checkbox_uncheck"></i> Unselect all</a> &nbsp;
<a href="#" onclick="invert_all();return false;"><i class="icon-checkbox_invert"></i> Invert selection</a></p>
<p><input type="submit" name="delete" value="Delete" onclick="return confirm('Delete selected files and folders?')">
<input type="submit" name="zip" value="Pack" onclick="return confirm('Create archive?')">
<input type="submit" name="copy" value="Copy"></p>
</form>

<?php
fm_show_footer();

//--- END

// Functions

/**
 * Delete  file or folder (recursively)
 * @param string $path
 * @return bool
 */
function fm_rdelete($path)
{
    if (is_link($path)) {
        return unlink($path);
    } elseif (is_dir($path)) {
        $objects = scandir($path);
        $ok = true;
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (!fm_rdelete($path . '/' . $file)) {
                        $ok = false;
                    }
                }
            }
        }
        return ($ok) ? rmdir($path) : false;
    } elseif (is_file($path)) {
        return unlink($path);
    }
    return false;
}

/**
 * Recursive chmod
 * @param string $path
 * @param int $filemode
 * @param int $dirmode
 * @return bool
 * @todo Will use in mass chmod
 */
function fm_rchmod($path, $filemode, $dirmode)
{
    if (is_dir($path)) {
        if (!chmod($path, $dirmode)) {
            return false;
        }
        $objects = scandir($path);
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (!fm_rchmod($path . '/' . $file, $filemode, $dirmode)) {
                        return false;
                    }
                }
            }
        }
        return true;
    } elseif (is_link($path)) {
        return true;
    } elseif (is_file($path)) {
        return chmod($path, $filemode);
    }
    return false;
}

/**
 * Safely rename
 * @param string $old
 * @param string $new
 * @return bool|null
 */
function fm_rename($old, $new)
{
    return (!file_exists($new) && file_exists($old)) ? rename($old, $new) : null;
}

/**
 * Copy file or folder (recursively).
 * @param string $path
 * @param string $dest
 * @param bool $upd Update files
 * @param bool $force Create folder with same names instead file
 * @return bool
 */
function fm_rcopy($path, $dest, $upd = true, $force = true)
{
    if (is_dir($path)) {
        if (!fm_mkdir($dest, $force)) {
            return false;
        }
        $objects = scandir($path);
        $ok = true;
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (!fm_rcopy($path . '/' . $file, $dest . '/' . $file)) {
                        $ok = false;
                    }
                }
            }
        }
        return $ok;
    } elseif (is_file($path)) {
        return fm_copy($path, $dest, $upd);
    }
    return false;
}

/**
 * Safely create folder
 * @param string $dir
 * @param bool $force
 * @return bool
 */
function fm_mkdir($dir, $force)
{
    if (file_exists($dir)) {
        if (is_dir($dir)) {
            return $dir;
        } elseif (!$force) {
            return false;
        }
        unlink($dir);
    }
    return mkdir($dir, 0777, true);
}

/**
 * Safely copy file
 * @param string $f1
 * @param string $f2
 * @param bool $upd
 * @return bool
 */
function fm_copy($f1, $f2, $upd)
{
    $time1 = filemtime($f1);
    if (file_exists($f2)) {
        $time2 = filemtime($f2);
        if ($time2 >= $time1 && $upd) {
            return false;
        }
    }
    $ok = copy($f1, $f2);
    if ($ok) {
        touch($f2, $time1);
    }
    return $ok;
}

/**
 * Get mime type
 * @param string $file_path
 * @return mixed|string
 */
function fm_get_mime_type($file_path)
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file_path);
        finfo_close($finfo);
        return $mime;
    } elseif (function_exists('mime_content_type')) {
        return mime_content_type($file_path);
    } elseif (!stristr(ini_get('disable_functions'), 'shell_exec')) {
        $file = escapeshellarg($file_path);
        $mime = shell_exec('file -bi ' . $file);
        return $mime;
    } else {
        return '--';
    }
}

/**
 * HTTP Redirect
 * @param string $url
 * @param int $code
 */
function fm_redirect($url, $code = 302)
{
    header('Location: ' . $url, true, $code);
    exit;
}

/**
 * Clean path
 * @param string $path
 * @return string
 */
function fm_clean_path($path)
{
    $path = trim($path);
    $path = trim($path, '\\/');
    $path = str_replace(array('../', '..\\'), '', $path);
    if ($path == '..') {
        $path = '';
    }
    return str_replace('\\', '/', $path);
}

/**
 * Get parent path
 * @param string $path
 * @return bool|string
 */
function fm_get_parent_path($path)
{
    $path = fm_clean_path($path);
    if ($path != '') {
        $array = explode('/', $path);
        if (count($array) > 1) {
            $array = array_slice($array, 0, -1);
            return implode('/', $array);
        }
        return '';
    }
    return false;
}

/**
 * Get nice filesize
 * @param int $size
 * @return string
 */
function fm_get_filesize($size)
{
    if ($size < 1000) {
        return sprintf('%s B', $size);
    } elseif (($size / 1024) < 1000) {
        return sprintf('%s KiB', round(($size / 1024), 2));
    } elseif (($size / 1024 / 1024) < 1000) {
        return sprintf('%s MiB', round(($size / 1024 / 1024), 2));
    } elseif (($size / 1024 / 1024 / 1024) < 1000) {
        return sprintf('%s GiB', round(($size / 1024 / 1024 / 1024), 2));
    } else {
        return sprintf('%s TiB', round(($size / 1024 / 1024 / 1024 / 1024), 2));
    }
}

/**
 * Get info about zip archive
 * @param string $path
 * @return array|bool
 */
function fm_get_zif_info($path)
{
    if (function_exists('zip_open')) {
        $arch = zip_open($path);
        if ($arch) {
            $filenames = array();
            while ($zip_entry = zip_read($arch)) {
                $zip_name = zip_entry_name($zip_entry);
                $zip_folder = substr($zip_name, -1) == '/';
                $filenames[] = array(
                    'name' => $zip_name,
                    'filesize' => zip_entry_filesize($zip_entry),
                    'compressed_size' => zip_entry_compressedsize($zip_entry),
                    'folder' => $zip_folder
                    //'compression_method' => zip_entry_compressionmethod($zip_entry),
                );
            }
            zip_close($arch);
            return $filenames;
        }
    }
    return false;
}

/**
 * Encode html entities
 * @param string $text
 * @return string
 */
function fm_enc($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Save message in session
 * @param string $msg
 * @param string $status
 */
function fm_set_msg($msg, $status = 'ok')
{
    $_SESSION['message'] = $msg;
    $_SESSION['status'] = $status;
}

/**
 * Check if string is in UTF-8
 * @param string $string
 * @return int
 */
function fm_is_utf8($string)
{
    return preg_match('//u', $string);
}

/**
 * Convert file name to UTF-8 in Windows
 * @param string $filename
 * @return string
 */
function fm_convert_win($filename)
{
    if (FM_IS_WIN && function_exists('iconv')) {
        $filename = iconv(FM_ICONV_INPUT_ENC, 'UTF-8//IGNORE', $filename);
    }
    return $filename;
}

/**
 * Get CSS classname for file
 * @param string $path
 * @return string
 */
function fm_get_file_icon_class($path)
{
    // get extension
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    switch ($ext) {
        case 'ico': case 'gif': case 'jpg': case 'jpeg': case 'jpc': case 'jp2':
        case 'jpx': case 'xbm': case 'wbmp': case 'png': case 'bmp': case 'tif':
        case 'tiff':
            $img = 'icon-file_image';
            break;
        case 'txt': case 'css': case 'ini': case 'conf': case 'log': case 'htaccess':
        case 'passwd': case 'ftpquota': case 'sql': case 'js': case 'json': case 'sh':
        case 'config': case 'twig': case 'tpl': case 'md': case 'gitignore':
        case 'less': case 'sass': case 'scss': case 'c': case 'cpp': case 'cs': case 'py':
        case 'map': case 'lock': case 'dtd':
            $img = 'icon-file_text';
            break;
        case 'zip': case 'rar': case 'gz': case 'tar': case '7z':
            $img = 'icon-file_zip';
            break;
        case 'php': case 'php4': case 'php5': case 'phps': case 'phtml':
            $img = 'icon-file_php';
            break;
        case 'htm': case 'html': case 'shtml': case 'xhtml':
            $img = 'icon-file_html';
            break;
        case 'xml': case 'xsl': case 'svg':
            $img = 'icon-file_code';
            break;
        case 'wav': case 'mp3': case 'mp2': case 'm4a': case 'aac': case 'ogg':
        case 'oga': case 'wma': case 'mka': case 'flac': case 'ac3': case 'tds':
            $img = 'icon-file_music';
            break;
        case 'm3u': case 'm3u8': case 'pls': case 'cue':
            $img = 'icon-file_playlist';
            break;
        case 'avi': case 'mpg': case 'mpeg': case 'mp4': case 'm4v': case 'flv':
        case 'f4v': case 'ogm': case 'ogv': case 'mov': case 'mkv': case '3gp':
        case 'asf': case 'wmv':
            $img = 'icon-file_film';
            break;
        case 'eml': case 'msg':
            $img = 'icon-file_outlook';
            break;
        case 'xls': case 'xlsx':
            $img = 'icon-file_excel';
            break;
        case 'csv':
            $img = 'icon-file_csv';
            break;
        case 'doc': case 'docx':
            $img = 'icon-file_word';
            break;
        case 'ppt': case 'pptx':
            $img = 'icon-file_powerpoint';
            break;
        case 'ttf': case 'ttc': case 'otf': case 'woff':case 'woff2': case 'eot': case 'fon':
            $img = 'icon-file_font';
            break;
        case 'pdf':
            $img = 'icon-file_pdf';
            break;
        case 'psd':
            $img = 'icon-file_photoshop';
            break;
        case 'ai': case 'eps':
            $img = 'icon-file_illustrator';
            break;
        case 'fla':
            $img = 'icon-file_flash';
            break;
        case 'swf':
            $img = 'icon-file_swf';
            break;
        case 'exe': case 'msi':
            $img = 'icon-file_application';
            break;
        case 'bat':
            $img = 'icon-file_terminal';
            break;
        default:
            $img = 'icon-document';
    }

    return $img;
}

/**
 * Get image files extensions
 * @return array
 */
function fm_get_image_exts()
{
    return array('ico', 'gif', 'jpg', 'jpeg', 'jpc', 'jp2', 'jpx', 'xbm', 'wbmp', 'png', 'bmp', 'tif', 'tiff', 'psd');
}

/**
 * Get video files extensions
 * @return array
 */
function fm_get_video_exts()
{
    return array('webm', 'mp4', 'm4v', 'ogm', 'ogv', 'mov');
}

/**
 * Get audio files extensions
 * @return array
 */
function fm_get_audio_exts()
{
    return array('wav', 'mp3', 'ogg', 'm4a');
}

/**
 * Get text file extensions
 * @return array
 */
function fm_get_text_exts()
{
    return array(
        'txt', 'css', 'ini', 'conf', 'log', 'htaccess', 'passwd', 'ftpquota', 'sql', 'js', 'json', 'sh', 'config',
        'php', 'php4', 'php5', 'phps', 'phtml', 'htm', 'html', 'shtml', 'xhtml', 'xml', 'xsl', 'm3u', 'm3u8', 'pls', 'cue',
        'eml', 'msg', 'csv', 'bat', 'twig', 'tpl', 'md', 'gitignore', 'less', 'sass', 'scss', 'c', 'cpp', 'cs', 'py',
        'map', 'lock', 'dtd', 'svg',
    );
}

/**
 * Get mime types of text files
 * @return array
 */
function fm_get_text_mimes()
{
    return array(
        'application/xml',
        'application/javascript',
        'application/x-javascript',
        'image/svg+xml',
        'message/rfc822',
    );
}

/**
 * Get file names of text files w/o extensions
 * @return array
 */
function fm_get_text_names()
{
    return array(
        'license',
        'readme',
        'authors',
        'contributors',
        'changelog',
    );
}

/**
 * Class to work with zip files (using ZipArchive)
 */
class FM_Zipper
{
    private $zip;

    public function __construct()
    {
        $this->zip = new ZipArchive();
    }

    /**
     * Create archive with name $filename and files $files (RELATIVE PATHS!)
     * @param string $filename
     * @param array|string $files
     * @return bool
     */
    public function create($filename, $files)
    {
        $res = $this->zip->open($filename, ZipArchive::CREATE);
        if ($res !== true) {
            return false;
        }
        if (is_array($files)) {
            foreach ($files as $f) {
                if (!$this->addFileOrDir($f)) {
                    $this->zip->close();
                    return false;
                }
            }
            $this->zip->close();
            return true;
        } else {
            if ($this->addFileOrDir($files)) {
                $this->zip->close();
                return true;
            }
            return false;
        }
    }

    /**
     * Extract archive $filename to folder $path (RELATIVE OR ABSOLUTE PATHS)
     * @param string $filename
     * @param string $path
     * @return bool
     */
    public function unzip($filename, $path)
    {
        $res = $this->zip->open($filename);
        if ($res !== true) {
            return false;
        }
        if ($this->zip->extractTo($path)) {
            $this->zip->close();
            return true;
        }
        return false;
    }

    /**
     * Add file/folder to archive
     * @param string $filename
     * @return bool
     */
    private function addFileOrDir($filename)
    {
        if (is_file($filename)) {
            return $this->zip->addFile($filename);
        } elseif (is_dir($filename)) {
            return $this->addDir($filename);
        }
        return false;
    }

    /**
     * Add folder recursively
     * @param string $path
     * @return bool
     */
    private function addDir($path)
    {
        if (!$this->zip->addEmptyDir($path)) {
            return false;
        }
        $objects = scandir($path);
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (is_dir($path . '/' . $file)) {
                        if (!$this->addDir($path . '/' . $file)) {
                            return false;
                        }
                    } elseif (is_file($path . '/' . $file)) {
                        if (!$this->zip->addFile($path . '/' . $file)) {
                            return false;
                        }
                    }
                }
            }
            return true;
        }
        return false;
    }
}

//--- templates functions

/**
 * Show nav block
 * @param string $path
 */
function fm_show_nav_path($path)
{
    ?>
<div class="path">
<div class="float-right">
<a title="Upload files" href="?p=<?php echo urlencode(FM_PATH) ?>&amp;upload"><i class="icon-upload"></i></a>
<a title="New folder" href="#" onclick="newfolder('<?php echo fm_enc(FM_PATH) ?>');return false;"><i class="icon-folder_add"></i></a>
<?php if (FM_USE_AUTH): ?><a title="Logout" href="?logout=1"><i class="icon-logout"></i></a><?php endif; ?>
</div>
        <?php
        $path = fm_clean_path($path);
        $root_url = "<a href='?p='><i class='icon-home' title='" . FM_ROOT_PATH . "'></i></a>";
        $sep = '<i class="icon-separator"></i>';
        if ($path != '') {
            $exploded = explode('/', $path);
            $count = count($exploded);
            $array = array();
            $parent = '';
            for ($i = 0; $i < $count; $i++) {
                $parent = trim($parent . '/' . $exploded[$i], '/');
                $parent_enc = urlencode($parent);
                $array[] = "<a href='?p={$parent_enc}'>" . fm_enc(fm_convert_win($exploded[$i])) . "</a>";
            }
            $root_url .= $sep . implode($sep, $array);
        }
        echo '<div class="break-word">' . $root_url . '</div>';
        ?>
</div>
<?php
}

/**
 * Show message from session
 */
function fm_show_message()
{
    if (isset($_SESSION['message'])) {
        $class = isset($_SESSION['status']) ? $_SESSION['status'] : 'ok';
        echo '<p class="message ' . $class . '">' . $_SESSION['message'] . '</p>';
        unset($_SESSION['message']);
        unset($_SESSION['status']);
    }
}

/**
 * Show page header
 */
function fm_show_header()
{
    $sprites_ver = '20160315';
    header("Content-Type: text/html; charset=utf-8");
    header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");
    header("Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0");
    header("Pragma: no-cache");
    ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>PHP File Manager</title>
<style>
html,body,div,span,p,pre,a,code,em,img,small,strong,ol,ul,li,form,label,table,tr,th,td{margin:0;padding:0;vertical-align:baseline;outline:none;font-size:100%;background:transparent;border:none;text-decoration:none}
html{overflow-y:scroll}body{padding:0;font:13px/16px Tahoma,Arial,sans-serif;color:#222;background:#efefef}
input,select,textarea,button{font-size:inherit;font-family:inherit}
a{color:#296ea3;text-decoration:none}a:hover{color:#b00}img{vertical-align:middle;border:none}
a img{border:none}span.gray{color:#777}small{font-size:11px;color:#999}p{margin-bottom:10px}
ul{margin-left:2em;margin-bottom:10px}ul{list-style-type:none;margin-left:0}ul li{padding:3px 0}
table{border-collapse:collapse;border-spacing:0;margin-bottom:10px;width:100%}
th,td{padding:4px 7px;text-align:left;vertical-align:top;border:1px solid #ddd;background:#fff;white-space:nowrap}
th,td.gray{background-color:#eee}td.gray span{color:#222}
tr:hover td{background-color:#f5f5f5}tr:hover td.gray{background-color:#eee}
code,pre{display:block;margin-bottom:10px;font:13px/16px Consolas,'Courier New',Courier,monospace;border:1px dashed #ccc;padding:5px;overflow:auto}
pre.with-hljs{padding:0}
pre.with-hljs code{margin:0;border:0;overflow:visible}
code.maxheight,pre.maxheight{max-height:512px}input[type="checkbox"]{margin:0;padding:0}
#wrapper{max-width:1000px;min-width:400px;margin:10px auto}
.path{padding:4px 7px;border:1px solid #ddd;background-color:#fff;margin-bottom:10px}
.right{text-align:right}.center{text-align:center}.float-right{float:right}
.message{padding:4px 7px;border:1px solid #ddd;background-color:#fff}
.message.ok{border-color:green;color:green}
.message.error{border-color:red;color:red}
.message.alert{border-color:orange;color:orange}
.btn{border:0;background:none;padding:0;margin:0;font-weight:bold;color:#296ea3;cursor:pointer}.btn:hover{color:#b00}
.preview-img{max-width:100%;background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAKklEQVR42mL5//8/Azbw+PFjrOJMDCSCUQ3EABZc4S0rKzsaSvTTABBgAMyfCMsY4B9iAAAAAElFTkSuQmCC") repeat 0 0}
.preview-video{position:relative;max-width:100%;height:0;padding-bottom:62.5%;margin-bottom:10px}.preview-video video{position:absolute;width:100%;height:100%;left:0;top:0;background:#000}
[class*="icon-"]{display:inline-block;width:16px;height:16px;background:url("<?php echo FM_SELF_URL ?>?img=sprites&amp;t=<?php echo $sprites_ver ?>") no-repeat 0 0;vertical-align:bottom}
.icon-document{background-position:-16px 0}.icon-folder{background-position:-32px 0}
.icon-folder_add{background-position:-48px 0}.icon-upload{background-position:-64px 0}
.icon-arrow_up{background-position:-80px 0}.icon-home{background-position:-96px 0}
.icon-separator{background-position:-112px 0}.icon-cross{background-position:-128px 0}
.icon-copy{background-position:-144px 0}.icon-apply{background-position:-160px 0}
.icon-cancel{background-position:-176px 0}.icon-rename{background-position:-192px 0}
.icon-checkbox{background-position:-208px 0}.icon-checkbox_invert{background-position:-224px 0}
.icon-checkbox_uncheck{background-position:-240px 0}.icon-download{background-position:-256px 0}
.icon-goback{background-position:-272px 0}.icon-folder_open{background-position:-288px 0}
.icon-file_application{background-position:0 -16px}.icon-file_code{background-position:-16px -16px}
.icon-file_csv{background-position:-32px -16px}.icon-file_excel{background-position:-48px -16px}
.icon-file_film{background-position:-64px -16px}.icon-file_flash{background-position:-80px -16px}
.icon-file_font{background-position:-96px -16px}.icon-file_html{background-position:-112px -16px}
.icon-file_illustrator{background-position:-128px -16px}.icon-file_image{background-position:-144px -16px}
.icon-file_music{background-position:-160px -16px}.icon-file_outlook{background-position:-176px -16px}
.icon-file_pdf{background-position:-192px -16px}.icon-file_photoshop{background-position:-208px -16px}
.icon-file_php{background-position:-224px -16px}.icon-file_playlist{background-position:-240px -16px}
.icon-file_powerpoint{background-position:-256px -16px}.icon-file_swf{background-position:-272px -16px}
.icon-file_terminal{background-position:-288px -16px}.icon-file_text{background-position:-304px -16px}
.icon-file_word{background-position:-320px -16px}.icon-file_zip{background-position:-336px -16px}
.icon-logout{background-position:-304px 0}.icon-chain{background-position:-320px 0}
.icon-link_folder{background-position:-352px -16px}.icon-link_file{background-position:-368px -16px}
.compact-table{border:0;width:auto}.compact-table td,.compact-table th{width:100px;border:0;text-align:center}.compact-table tr:hover td{background-color:#fff}
.filename{max-width:420px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.break-word{word-wrap:break-word}
</style>
<link rel="icon" href="<?php echo FM_SELF_URL ?>?img=favicon" type="image/png">
<link rel="shortcut icon" href="<?php echo FM_SELF_URL ?>?img=favicon" type="image/png">
<?php if (isset($_GET['view']) && FM_USE_HIGHLIGHTJS): ?>
<link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/highlight.js/9.2.0/styles/<?php echo FM_HIGHLIGHTJS_STYLE ?>.min.css">
<?php endif; ?>
</head>
<body>
<div id="wrapper">
<?php
}

/**
 * Show page footer
 */
function fm_show_footer()
{
    ?>
<p class="center"><small><a href="https://github.com/alexantr/filemanager" target="_blank">PHP File Manager</a></small></p>
</div>
<script>
function newfolder(p){var n=prompt('New folder name','folder');if(n!==null&&n!==''){window.location.search='p='+encodeURIComponent(p)+'&new='+encodeURIComponent(n);}}
function rename(p,f){var n=prompt('New name',f);if(n!==null&&n!==''&&n!=f){window.location.search='p='+encodeURIComponent(p)+'&ren='+encodeURIComponent(f)+'&to='+encodeURIComponent(n);}}
function change_checkboxes(l,v){for(var i=l.length-1;i>=0;i--){l[i].checked=(typeof v==='boolean')?v:!l[i].checked;}}
function get_checkboxes(){var i=document.getElementsByName('file[]'),a=[];for(var j=i.length-1;j>=0;j--){if(i[j].type='checkbox'){a.push(i[j]);}}return a;}
function select_all(){var l=get_checkboxes();change_checkboxes(l,true);}
function unselect_all(){var l=get_checkboxes();change_checkboxes(l,false);}
function invert_all(){var l=get_checkboxes();change_checkboxes(l);}
function checkbox_toggle(){var l=get_checkboxes();l.push(this);change_checkboxes(l);}
</script>
<?php if (isset($_GET['view']) && FM_USE_HIGHLIGHTJS): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/9.2.0/highlight.min.js"></script>
<script>hljs.initHighlightingOnLoad();</script>
<?php endif; ?>
</body>
</html>
<?php
}

/**
 * Show image
 * @param string $img
 */
function fm_show_image($img)
{
    $modified_time = gmdate('D, d M Y 00:00:00') . ' GMT';
    $expires_time = gmdate('D, d M Y 00:00:00', strtotime('+1 day')) . ' GMT';

    $img = trim($img);
    $images = fm_get_images();
    $image = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAEElEQVR42mL4//8/A0CAAQAI/AL+26JNFgAAAABJRU5ErkJggg==';
    if (isset($images[$img])) {
        $image = $images[$img];
    }
    $image = base64_decode($image);
    if (function_exists('mb_strlen')) {
        $size = mb_strlen($image, '8bit');
    } else {
        $size = strlen($image);
    }

    if (function_exists('header_remove')) {
        header_remove('Cache-Control');
        header_remove('Pragma');
    } else {
        header('Cache-Control:');
        header('Pragma:');
    }

    header('Last-Modified: ' . $modified_time, true, 200);
    header('Expires: ' . $expires_time);
    header('Content-Length: ' . $size);
    header('Content-Type: image/png');
    echo $image;

    exit;
}

/**
 * Get base64-encoded images
 * @return array
 */
function fm_get_images()
{
    return array(
        'favicon' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAZVJREFUeNqkk79Lw0AUx1+uidTQim4Waxfpnl1BcHMR6uLkIF0cpYOI
f4KbOFcRwbGTc0HQSVQQXCqlFIXgFkhIyvWS870LaaPYH9CDy8vdfb+fey930aSUMEvT6VHVzw8x
rKUX3N3Hj/8M+cZ6GcOtBPl6KY5iAA7KJzfVWrfbhUKhALZtQ6myDf1+X5nsuzjLUmUOnpa+v5r1
Z4ZDDfsLiwER45xDEATgOI6KntfDd091GidzC8vZ4vH1QQ09+4MSMAMWRREKPMhmsyr6voYmrnb2
PKEizdEabUaeFCDKCCHAdV0wTVNFznMgpVqGlZ2cipzHGtKSZwCIZJgJwxB38KHT6Sjx21V75Jcn
LXmGAKTRpGVZUx2dAqQzSEqw9kqwuGqONTufPrw37D8lQFxCvjgPXIixANLEGfwuQacMOC4kZz+q
GdhJS550BjpRCdCbAJCMJRkMASEIg+4Bxz4JwAwDSEueAYDLIM+QrOk6GHiRxjXSkJY8KUCvdXZ6
kbuvNx+mOcbN9taGBlpLAWf9nX8EGADoCfqkKWV/cgAAAABJRU5ErkJggg==',
        'sprites' => 'iVBORw0KGgoAAAANSUhEUgAAAYAAAAAgCAMAAAAscl/XAAAC/VBMVEUAAABUfn4KKipIcXFSeXsx
VlZSUlNAZ2c4Xl4lSUkRDg7w8O/d3d3LhwAWFhYXODgMLCx8fHw9PT2TtdOOAACMXgE8lt+dmpq+
fgABS3RUpN+VUycuh9IgeMJUe4C5dUI6meKkAQEKCgoMWp5qtusJmxSUPgKudAAXCghQMieMAgIU
abNSUlJLe70VAQEsh85oaGjBEhIBOGxfAoyUbUQAkw8gui4LBgbOiFPHx8cZX6PMS1OqFha/MjIK
VKFGBABSAXovGAkrg86xAgIoS5Y7c6Nf7W1Hz1NmAQB3Hgx8fHyiTAAwp+eTz/JdDAJ0JwAAlxCQ
UAAvmeRiYp6ysrmIAABJr/ErmiKmcsATpRyfEBAOdQgOXahyAAAecr1JCwHMiABgfK92doQGBgZG
AGkqKiw0ldYuTHCYsF86gB05UlJmQSlra2tVWED////8/f3t9fX5/Pzi8/Px9vb2+/v0+fnn8vLf
7OzZ6enV5+eTpKTo6Oj6/v765Z/U5eX4+Pjx+Pjv0ojWBASxw8O8vL52dnfR19CvAADR3PHr6+vi
4uPDx8v/866nZDO7iNT335jtzIL+7aj86aTIztXDw8X13JOlpKJoaHDJAACltratrq3lAgKfAADb
4vb76N2au9by2I9gYGVIRkhNTE90wfXq2sh8gL8QMZ3pyn27AADr+uu1traNiIh2olTTshifodQ4
ZM663PH97+YeRq2GqmRjmkGjnEDnfjLVVg6W4f7s6/p/0fr98+5UVF6wz+SjxNsmVb5RUVWMrc7d
zrrIpWI8PD3pkwhCltZFYbNZja82wPv05NPRdXzhvna4uFdIiibPegGQXankxyxe0P7PnOhTkDGA
gBrbhgR9fX9bW1u8nRFamcgvVrACJIvlXV06nvtdgON4mdn3og7AagBTufkucO7snJz4b28XEhIT
sflynsLEvIk55kr866aewo2YuYDrnFffOTk6Li6hgAn3y8XkusCHZQbt0NP571lqRDZyMw96lZXE
s6qcrMmJaTmVdRW2AAAAbnRSTlMAZodsJHZocHN7hP77gnaCZWdx/ki+RfqOd/7+zc9N/szMZlf8
z8yeQybOzlv+tP5q/qKRbk78i/vZmf798s3MojiYjTj+/vqKbFc2/vvMzJiPXPzbs4z9++bj1XbN
uJxhyMBWwJbp28C9tJ6L1xTnMfMAAA79SURBVGje7Jn5b8thHMcfzLDWULXq2upqHT2kbrVSrJYx
NzHmviWOrCudqxhbNdZqHauKJTZHm0j0ByYkVBCTiC1+EH6YRBY/EJnjD3D84PMc3++39Z1rjp+8
Kn189rT5Pt/363k+3YHEDOrCSKP16t48q8U1IysLAUKZk1obLBYDKjAUoB8ziLv4vyQLQD+Lcf4Q
jvno90kfDaQTRhcioIv7QPk2oJqF0PsIT29RzQdOEhfKG6QW8lcoLIYxjWPQD2GXr/63BhYsWrQA
fYc0JSaNxa8dH4zUEYag32f009DTkNTnC4WkpcRAl4ryHTt37d5/ugxCIIEfZ0Dg4poFThIXygSp
hfybmhSWLS0dCpDrdFMRZubUkmJ2+d344qIU8sayN8iFQaBgMDy+FWA/wjelOmbrHUKVtQgxFqFc
JeE2RpmLEIlfFazzer3hcOAPCQiFasNheAo9HQ1f6FZRTgzs2bOnFwn8+AnG8d6impClTkSjCXWW
kH80GmUGWP6A4kKkQwG616/tOhin6kii3dzl5YHqT58+bf5KQdq8IjCAg3+tk3NDCoPZC2fQuGcI
7+8nKQMk/b41r048UKOk48zln4MgesydOw0NDbeVCA2B+FVaEIDz/0MCSkOlAa+3tDRQSgW4t1MD
+7d1Q8DA9/sY7weKapZ/Qp+tzwYDtLyRiOrBANQ0/3hTMBIJNsXPb0GM5ANfrLO3telmTrWXGBG7
fHVHbWjetKKiPCJsAkQv17VNaANv6zJTWAcvmCEtI0hnII4RLsIIBIjmHStXaqKzNCtXOvj+STxl
OXKwgDuEBuAOEQDxgwDIv85bCwKMw6B5DzOyoVMCHpc+Dnu9gUD4MSeAGWACTnCBnxgorgGHRqPR
Z8OTg5ZqtRoEwLODy79JdfiwqgkMGBAlJ4caYK3HNGGCHedPBLgqtld30IbmLZk2jTsB9jadboJ9
Aj4BMqlAXCqV4e3udGH8zn6CgMrtQCUIoPMEbj5Xk3jS3N78UpPL7R81kJOTHdU7QACff/9kAbD/
IxHvEGTcmi/1+/NlMjJsNXZKAAcIoAkwA0zAvqOMfQNFNcOsf2BGAppotl6D+P0fi6nOnFHFYk1x
CzOgvqEGA4ICk91uQpQee90V1W58fdYDx0Ls+JnmTwy02e32iRNJB5L5X7y4/Pzq1buXX/lb/X4Z
SRtTo4C8uf6/Nez11dRI0pkNCswzA+Yn7e3NZi5/aKcYaKPqLBDw5iHPKGUutCAQoKqri0QizsgW
lJ6/1mqNK4C41bo2P72TnwEMEEASYAa29SCBHz1J2fdo4ExRTbHl5NiSBWQ/yGYCLBnFLbFY8PPn
YCzWUpxhYS9IJDSIx1iydKJpKTPQ0+lyV9MuCEcQJw+tH57Hjcubhyhy00TAJEdAuocX4Gn1eNJJ
wHG/xB+PQ8BC/6/0ejw1nAAJAeZ5A83tNH+kuaHHZD8A1MsRUvZ/c0WgPwhQBbGAiAQz2CjzZSJr
GOxKw1aU6ZOhX2ZK6GYZ42ZoChbgdDED5UzAWcLRR4+cA0U1ZfmiRcuRgJkIYIwBARThuyDzE7hf
nulLR5qKS5aWMAFOV7WrghjAAvKKpoEByH8J5C8WMELCC5AckkhGYCeS1lZfa6uf2/AuoM51yePB
DYrM18AD/sE8Z2DSJLaeLHNCr385C9iowbekfHOvQWBN4dzxXhUIuIRPgD+yCskWrs3MOETIyFy7
sFMC9roYe0EA2YLMwIGeCBh68iDh5P2TFUOhzhs3LammFC5YUIgEVmY/mKVJ4wTUx2JvP358G4vV
8wLo/TKKl45cWgwaTNNx1b3M6TwNh5DuANJ7xk37Kv+RBDCAtzMvoPJUZSUVID116pTUw3ecyPZI
vHIzfEQXMAEeAszzpKUhoR81m4GVNnJHyocN/Xnu2NLmaj/CEVBdqvX5FArvXGTYoAhIaxUb2GDo
jAD3doabCeAMVFABZ6mAs/fP7sCBLykal1KjYemMYYhh2zgrWUBLi2r8eFVLiyDAlpS/ccXIkSXk
IJTIiYAy52l8COkOoAZE+ZtMzEA/p8ApJ/lcldX4fc98fn8Nt+Fhd/Lbnc4DdF68fjgNzZMQhQkQ
UKK52mAQC/D5fHVe6VyEDBlWqzXDwAbUGQEHdjAOgACcAGegojsRcPAY4eD9g7uGonl5S4oWL77G
17D+fF/AewmzkDNQaG5v1+SmCtASAWKgAVWtKKD/w0egD/TC005igO2AsctAQB6/RU1VVVUmuZwM
CM3oJ2CB7+1xwPkeQj4TUOM5x/o/IJoXrR8MJAkY9ab/PZ41uZwAr88nBUDA7wICyncyypkAzoCb
CbhIgMCbh6K8d5jFfA3346qUePywmtrDfAdcrmmfZeMENNbXq7Taj/X1Hf8qYk7VxOlcMwIRfbt2
7bq5jBqAHUANLFlmRBzyFVUr5NyQgoUdqcGZhMFGmrfUA5D+L57vcP25thQBArZCIkCl/eCF/IE5
6PdZHzqwjXEgtB6+0KuMM+DuRQQcowKO3T/WjE/A4ndwAmhNBXjq4q1wyluLamWIN2Aebl4uCAhq
x2u/JUA+Z46Ri4aeBLYHYAEggBooSHmDXBgE1lnggcQU0LgLUMekrl+EclQSSgQCVFrVnFWTKav+
xAlY35Vn/RTSA4gB517X3j4IGMC1oOsHB8yEetm7xSl15kL4TVIAfjDxKjIRT6Ft0iQb3da3GhuD
QGPjrWL0E7AlsAX8ZUTr/xFzIP7pRvQ36SsI6Yvr+QN45uN607JlKbUhg8eAOgB2S4bFarVk/PyG
6Sss4O/y4/WL7+avxS/+e8D/+ku31tKbRBSFXSg+6iOpMRiiLrQ7JUQ3vhIXKks36h/QhY+FIFJ8
pEkx7QwdxYUJjRC1mAEF0aK2WEActVVpUbE2mBYp1VofaGyibW19LDSeOxdm7jCDNI0rv0lIvp7v
nnPnHKaQ+zHV/sxcPlPZT5Hrp69SEVg1vdgP+C/58cOT00+5P2pKreynyPWr1s+Ff4EOOzpctTt2
rir2A/bdxPhSghfrt9TxcCVlcWU+r5NH+ukk9fu6MYZL1NtwA9De3n6/dD4GA/N1EYwRxXzl+7NL
i/FJUo9y0Mp+inw/Kgp9BwZz5wxArV5e7AfcNGDcLMGL9XXnEOpcAVlcmXe+QYAJTFLfbcDoLlGv
/QaeQKiwfusuH8BB5EMnfYcKPGLAiCjmK98frQFDK9kvNZdW9lPk96cySKAq9gOCxmBw7hd4LcGl
enQDBsOoAW5AFlfkMICnhqdvDJ3pSerDRje8/93GMM9xwwznhHowAINhCA0gz5f5MOxiviYG8K4F
XoBHjO6RkdNuY4TI9wFuoZBPFfd6vR6EOAIaQHV9vaO+sJ8Ek7gAF5OQ7JeqoJX9FPn9qYwSqIr9
gGB10BYMfqkOluBIr6Y7AHQz4q4667k6q8sVIOI4n5zjARjfGDtH0j1E/FoepP4dg+Nha/fwk+Fu
axj0uN650e+vxHqhG6YbptcmbSjPd13H8In5TRaU7+Ix4GgAI5Fx7qkxIuY7N54T86m89mba6WTZ
Do/H2+HhB3Cstra2sP9EdSIGV3VCcn+Umlb2U+T9UJmsBEyqYj+gzWJrg8vSVoIjPW3vWLjQY6fx
DXDcKOcKNBBxyFdTQ3KmSqOpauF5upPjuE4u3UPEhQGI66FhR4/iAYQfwGUNgx7Xq3v1anxUqBdq
j8WG7mlD/jzfcf0jf+0Q8s9saoJnYFBzkWHgrC9qjUS58RFrVMw3ynE5IZ/Km2lsZtmMF9p/544X
DcAEDwDAXo/iA5bEXd9dn2VAcr/qWlrZT5H7LSqrmYBVxfsBc5trTjbbeD+g7crNNuj4lTZYocSR
nqa99+97aBrxgKvV5WoNNDTgeMFfSCYJzmi2ATQtiKfTrZ2t6daeHiLeD81PpVLXiPVmaBgfD1eE
hy8Nwyvocb1X7tx4a7JQz98eg/8/sYQ/z3cXngDJfizm94feHzqMBsBFotFohIsK+Vw5t0vcv8pD
0SzVjPvPdixH648eO1YLmIviUMp33Xc9FpLkp2i1sp8i91sqzRUEzJUgMNbQdrPZTtceBEHvlc+f
P/f2XumFFUoc6Z2Nnvu/4o1OxBsC7kAgl2s4T8RN1RPJ5ITIP22rulXVsi2LeE/aja6et4T+Zxja
/yOVEtfzDePjfRW2cF/YVtGH9LhebuPqBqGeP9QUCjVd97/M82U7fAg77EL+WU0Igy2DDDMLDeBS
JBq5xEWFfDl3MiDmq/R0wNvfy7efdd5BAzDWow8Bh6OerxdLDDgGHDE/eb9oAsp+itxvqaw4QaCi
Eh1HXz2DFGfOHp+FGo7RCyuUONI7nZ7MWNzpRLwhj/NE3GRKfp9Iilyv0XVpuqr0iPfk8ZbQj/2E
/v/4kQIu+BODhwYhjgaAN9oHeqV6L/0YLwv5tu7dAXCYJfthtg22tPA8yrUicFHlfDCATKYD+o/a
74QBoPVHjuJnAOIwAAy/JD9Fk37K/auif0L6LRc38IfjNQRO8AOoYRthhuxJCyTY/wwjaKZpCS/4
BaBnG+NDQ/FGFvEt5zGSRNz4fSPgu8D1XTqdblCnR3zxW4yHhP7j2M/fT09dTgnr8w1DfFEfRhj0
SvXWvMTwYa7gb8yA97/unQ59F5oBJnsUI6KcDz0B0H/+7S8MwG6DR8Bhd6D4Jj9GQlqPogk/JZs9
K/gn5H40e7aL7oToUYAfYMvUnMw40Gkw4Q80O6XcLMRZFgYwxrKl4saJjabqjRMCf6QDdOkeldJ/
BfSnrvWLcWgYxGX6KfPswEKLZVL6yrgXvv6g9uMBoDic3B/9e36KLvDNS7TZ7K3sGdE/wfoqDQD9
NGG+9AmYL/MDRM5iLo9nqDEYAJWRx5U5o+3SaHRaplS8H+Faf78Yh4bJ8k2Vz24qgJldXj8/DkCf
wDy8fH/sdpujTD2KxhxM/ueA249E/wTru/Dfl05bPkeC5TI/QOAvbJjL47TnI8BDy+KlOJPV6bJM
yfg3wNf+r99KxafOibNu5IQvKKsv2x9lTtEFvmGlXq9/rFeL/gnWD2kB6KcwcpB+wP/IyeP2svqp
9oeiCT9Fr1cL/gmp125aUc4P+B85iX+qJ/la0k/Ze0D0T0j93jXTpv0BYUGhQhdSooYAAAAASUVO
RK5CYII=',
    );
}
