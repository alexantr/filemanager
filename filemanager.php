<?php
/**
 * PHP File Manager
 * Author: Alex Yashkin <alex.yashkin@gmail.com>
 */

//--- CONFIG

// Auth with login/password (set true/false to enable/disable it)
$use_auth = true;

// Users: array('Username' => 'Password', 'Username2' => 'Password2', ...)
$auth_users = array(
    'admin' => 'admin',
    'user' => '12345',
);

// Default timezone for date() and time() - http://php.net/manual/en/timezones.php
$default_timezone = 'Europe/Minsk'; // UTC+3

// Default language (en, ru, fr)
$lang = 'ru';

// Base folder (relative to document_root) ('', 'subfolder', 'subfolder/subfolder2' etc.)
$base_folder = '';

// Readonly users (usernames array)
$readonly_users = array(
    'user',
);

//--- END CONFIG

$languages = array('en', 'ru', 'fr');

error_reporting(E_ALL);
set_time_limit(600);

date_default_timezone_set($default_timezone);

ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
if (function_exists('mb_regex_encoding')) mb_regex_encoding('UTF-8');

session_cache_limiter('');
session_name('filemanager');
session_start();

$base_folder = clean_path($base_folder);
$is_https = isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] == 'on' || $_SERVER['HTTPS'] == 1)
    || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https';

// check $base_folder
$root_path = $_SERVER['DOCUMENT_ROOT'] . (!empty($base_folder) ? '/' . $base_folder : '');
if (!is_dir($root_path)) {
    $base_folder = '';
}

// abs path for site
define('ROOT_PATH', $_SERVER['DOCUMENT_ROOT'] . (!empty($base_folder) ? '/' . $base_folder : ''));
define('ROOT_URL', ($is_https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . (!empty($base_folder) ? '/' . $base_folder : ''));
define('FM_URL', ($is_https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);

// logout
if (isset($_GET['logout'])) {
    unset($_SESSION['logged']);
    redirect(FM_URL);
}

// Show image here
show_image();

// Auth
if ($use_auth && !empty($auth_users)) {
    if (isset($_SESSION['logged'], $auth_users[$_SESSION['logged']])) {
        // Logged
        $lang = (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $languages)) ? $_SESSION['lang'] : $lang;
    } elseif (isset($_POST['fm_usr']) && isset($_POST['fm_pwd'])) {
        // Logging In
        sleep(1);
        if (isset($auth_users[$_POST['fm_usr']]) && $_POST['fm_pwd'] === $auth_users[$_POST['fm_usr']]) {
            $_SESSION['logged'] = $_POST['fm_usr'];
            if (isset($_POST['lang']) && in_array($_POST['lang'], $languages)) {
                $_SESSION['lang'] = $_POST['lang'];
                $lang = $_POST['lang'];
            }
            set_message(__('You are logged in'));
            redirect(FM_URL . '?p=');
        } else {
            unset($_SESSION['logged']);
            set_message(__('Wrong password'), 'error');
            redirect(FM_URL);
        }
    } else {
        // Form
        unset($_SESSION['logged']);
        show_header();
        show_message();
        ?>
        <div class="path">
            <form action="" method="post" style="margin:10px;text-align:center">
                <input type="text" name="fm_usr" value="" placeholder="<?php _e('Username') ?>" required>
                <input type="password" name="fm_pwd" value="" placeholder="<?php _e('Password') ?>" required>
                <select name="lang">
                    <?php foreach ($languages as $l): ?>
                        <option value="<?php echo $l ?>"<?php if ($l == $lang) echo ' selected'; ?>><?php echo $l ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="submit" value="<?php _e('Login') ?>">
            </form>
        </div>
        <?php
        show_footer();
        exit;
    }
}

define('READONLY', $use_auth && !empty($auth_users) && !empty($readonly_users) && isset($_SESSION['logged']) && in_array($_SESSION['logged'], $readonly_users));

// always use ?p=
if (!isset($_GET['p'])) redirect(FM_URL . '?p=');

// get path
$p = isset($_GET['p']) ? $_GET['p'] : (isset($_POST['p']) ? $_POST['p'] : '');

// clean path
$p = clean_path($p);

/*************************** ACTIONS ***************************/

// Delete file / folder
if (isset($_GET['del']) && !READONLY) {
    $del = $_GET['del'];
    $del = clean_path($del);
    $del = str_replace('/', '', $del);
    if ($del != '' && $del != '..' && $del != '.') {
        $path = ROOT_PATH;
        if ($p != '') $path .= '/' . $p;
        $is_dir = (is_dir($path . '/' . $del)) ? true : false;
        if (rdelete($path . '/' . $del)) {
            $msg = $is_dir ? __('Folder <b>%s</b> deleted') : __('File <b>%s</b> deleted');
            set_message(sprintf($msg, $del));
        } else {
            $msg = $is_dir ? __('Folder <b>%s</b> not deleted') : __('File <b>%s</b> not deleted');
            set_message(sprintf($msg, $del), 'error');
        }
    } else {
        set_message(__('Wrong file or folder name'), 'error');
    }
    redirect(FM_URL . '?p=' . urlencode($p));
}

// Create folder
if (isset($_GET['new']) && !READONLY) {
    $new = $_GET['new'];
    $new = clean_path($new);
    $new = str_replace('/', '', $new);
    if ($new != '' && $new != '..' && $new != '.') {
        $path = ROOT_PATH;
        if ($p != '') $path .= '/' . $p;
        if (mkdir_safe($path . '/' . $new, false) === true) {
            set_message(sprintf(__('Folder <b>%s</b> created'), $new));
        } elseif (mkdir_safe($path . '/' . $new, false) === $path . '/' . $new) {
            set_message(sprintf(__('Folder <b>%s</b> already exists'), $new), 'alert');
        } else {
            set_message(sprintf(__('Folder <b>%s</b> not created'), $new), 'error');
        }
    } else {
        set_message(__('Wrong folder name'), 'error');
    }
    redirect(FM_URL . '?p=' . urlencode($p));
}

// Copy folder / file
if (isset($_GET['copy']) && isset($_GET['finish']) && !READONLY) {
    // from
    $copy = $_GET['copy'];
    $copy = clean_path($copy);
    // empty path
    if ($copy == '') {
        set_message(__('Source path not defined'), 'error');
        redirect(FM_URL . '?p=' . urlencode($p));
    }
    // abs path from
    $from = ROOT_PATH . '/' . $copy;
    // abs path to
    $dest = ROOT_PATH;
    if ($p != '') $dest .= '/' . $p;
    $dest .= '/' . basename($from);
    // move?
    $move = (isset($_GET['move'])) ? true : false;
    // copy/move
    if ($from != $dest) {
        $msg_from = trim($p . '/' . basename($from), '/');
        if ($move) {
            $rename = rename_safe($from, $dest);
            if ($rename) {
                set_message(sprintf(__('Moved from <b>%s</b> to <b>%s</b>'), $copy, $msg_from));
            } elseif ($rename === null) {
                set_message(__('File or folder with this path already exists'), 'alert');
            } else {
                set_message(sprintf(__('Error while moving from <b>%s</b> to <b>%s</b>'), $copy, $msg_from), 'error');
            }
        } else {
            if (rcopy($from, $dest)) {
                set_message(sprintf(__('Copyied from <b>%s</b> to <b>%s</b>'), $copy, $msg_from));
            } else {
                set_message(sprintf(__('Error while copying from <b>%s</b> to <b>%s</b>'), $copy, $msg_from), 'error');
            }
        }
    } else {
        set_message(__('Paths must be not equal'), 'alert');
    }
    redirect(FM_URL . '?p=' . urlencode($p));
}

// Mass copy files/ folders
if (isset($_POST['file']) && isset($_POST['copy_to']) && isset($_POST['finish']) && !READONLY) {
    // from
    $path = ROOT_PATH;
    if ($p != '') $path .= '/' . $p;
    // to
    $copy_to_path = ROOT_PATH;
    $copy_to = clean_path($_POST['copy_to']);
    if ($copy_to != '') $copy_to_path .= '/' . $copy_to;
    if ($path == $copy_to_path) {
        set_message(__('Paths must be not equal'), 'alert');
        redirect(FM_URL . '?p=' . urlencode($p));
    }
    if (!is_dir($copy_to_path)) {
        if (!mkdir_safe($copy_to_path, true)) {
            set_message(__('Unable to create destination folder'), 'error');
            redirect(FM_URL . '?p=' . urlencode($p));
        }
    }
    // move?
    $move = (isset($_POST['move'])) ? true : false;
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
                    $rename = rename_safe($from, $dest);
                    if ($rename === false) {
                        $errors++;
                    }
                } else {
                    if (!rcopy($from, $dest)) {
                        $errors++;
                    }
                }
            }
        }
        if ($errors == 0) {
            $msg = $move ? __('Selected files and folders moved') : __('Selected files and folders copied');
            set_message($msg);
        } else {
            $msg = $move ? __('Error while moving items') : __('Error while copying items');
            set_message($msg, 'error');
        }
    } else {
        set_message(__('Nothing selected'), 'alert');
    }
    redirect(FM_URL . '?p=' . urlencode($p));
}

// Rename
if (isset($_GET['ren']) && isset($_GET['to']) && !READONLY) {
    // old name
    $old = $_GET['ren'];
    $old = clean_path($old);
    $old = str_replace('/', '', $old);
    // new name
    $new = $_GET['to'];
    $new = clean_path($new);
    $new = str_replace('/', '', $new);
    // path
    $path = ROOT_PATH;
    if ($p != '') $path .= '/' . $p;
    // rename
    if ($old != '' && $new != '') {
        if (rename_safe($path . '/' . $old, $path . '/' . $new)) {
            set_message(sprintf(__('Renamed from <b>%s</b> to <b>%s</b>'), $old, $new));
        } else {
            set_message(sprintf(__('Error while renaming from <b>%s</b> to <b>%s</b>'), $old, $new), 'error');
        }
    } else {
        set_message(__('Names not set'), 'error');
    }
    redirect(FM_URL . '?p=' . urlencode($p));
}

// Download
if (isset($_GET['dl'])) {
    $dl = $_GET['dl'];
    $dl = clean_path($dl);
    $dl = str_replace('/', '', $dl);
    $path = ROOT_PATH;
    if ($p != '') $path .= '/' . $p;
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
        set_message(__('File not found'), 'error');
        redirect(FM_URL . '?p=' . urlencode($p));
    }
}

// Upload
if (isset($_POST['upl']) && !READONLY) {
    $path = ROOT_PATH;
    if ($p != '') $path .= '/' . $p;

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
        set_message(sprintf(__('All files uploaded to <b>%s</b>'), $path));
    } elseif ($errors == 0 && $uploads == 0) {
        set_message(__('Nothing uploaded'), 'alert');
    } else {
        set_message(sprintf(__('Error while uploading files. Uploaded files: %s'), $uploads), 'error');
    }

    redirect(FM_URL . '?p=' . urlencode($p));
}

// Mass deleting
if (isset($_POST['group']) && isset($_POST['delete']) && !READONLY) {
    $path = ROOT_PATH;
    if ($p != '') $path .= '/' . $p;

    $errors = 0;
    $files = $_POST['file'];
    if (is_array($files) && count($files)) {
        foreach ($files as $f) {
            if ($f != '') {
                $new_path = $path . '/' . $f;
                if (!rdelete($new_path)) {
                    $errors++;
                }
            }
        }
        if ($errors == 0) {
            set_message(__('Selected files and folder deleted'));
        } else {
            set_message(__('Error while deleting items'), 'error');
        }
    } else {
        set_message(__('Nothing selected'), 'alert');
    }

    redirect(FM_URL . '?p=' . urlencode($p));
}

// Pack files
if (isset($_POST['group']) && isset($_POST['zip']) && !READONLY) {
    $path = ROOT_PATH;
    if ($p != '') $path .= '/' . $p;

    if (!class_exists('ZipArchive')) {
        set_message(__('Operations with archives are not available'), 'error');
        redirect(FM_URL . '?p=' . urlencode($p));
    }

    $files = $_POST['file'];
    if (!empty($files)) {
        chdir($path);

        $zipname = 'archive_' . date('ymd_His') . '.zip';

        $zipper = new Zipper;
        $res = $zipper->create($zipname, $files);

        if ($res) {
            set_message(sprintf(__('Archive <b>%s</b> created'), $zipname));
        } else {
            set_message(__('Archive not created'), 'error');
        }
    } else {
        set_message(__('Nothing selected'), 'alert');
    }

    redirect(FM_URL . '?p=' . urlencode($p));
}

// Unpack
if (isset($_GET['unzip']) && !READONLY) {
    $unzip = $_GET['unzip'];
    $unzip = clean_path($unzip);
    $unzip = str_replace('/', '', $unzip);

    $path = ROOT_PATH;
    if ($p != '') $path .= '/' . $p;

    if (!class_exists('ZipArchive')) {
        set_message(__('Operations with archives are not available'), 'error');
        redirect(FM_URL . '?p=' . urlencode($p));
    }

    if ($unzip != '' && is_file($path . '/' . $unzip)) {
        $zip_path = $path . '/' . $unzip;

        //to folder
        $tofolder = '';
        if (isset($_GET['tofolder'])) {
            $tofolder = pathinfo($zip_path, PATHINFO_FILENAME);
            if (mkdir_safe($path . '/' . $tofolder, true)) {
                $path .= '/' . $tofolder;
            }
        }

        $zipper = new Zipper;
        $res = $zipper->unzip($zip_path, $path);

        if ($res) {
            set_message(__('Archive unpacked'));
        } else {
            set_message(__('Archive not unpacked'), 'error');
        }

    } else {
        set_message(__('File not found'), 'error');
    }
    redirect(FM_URL . '?p=' . urlencode($p));
}

// Change Perms
if (isset($_POST['chmod']) && !READONLY) {
    $path = ROOT_PATH;
    if ($p != '') $path .= '/' . $p;

    $file = $_POST['chmod'];
    $file = clean_path($file);
    $file = str_replace('/', '', $file);
    if ($file == '' || (!is_file($path . '/' . $file) && !is_dir($path . '/' . $file))) {
        set_message(__('File not found'), 'error');
        redirect(FM_URL . '?p=' . urlencode($p));
    }

    $mode = 0;
    if (isset($_POST['ur']) && !empty($_POST['ur'])) $mode |= 0400;
    if (isset($_POST['uw']) && !empty($_POST['uw'])) $mode |= 0200;
    if (isset($_POST['ux']) && !empty($_POST['ux'])) $mode |= 0100;
    if (isset($_POST['gr']) && !empty($_POST['gr'])) $mode |= 0040;
    if (isset($_POST['gw']) && !empty($_POST['gw'])) $mode |= 0020;
    if (isset($_POST['gx']) && !empty($_POST['gx'])) $mode |= 0010;
    if (isset($_POST['or']) && !empty($_POST['or'])) $mode |= 0004;
    if (isset($_POST['ow']) && !empty($_POST['ow'])) $mode |= 0002;
    if (isset($_POST['ox']) && !empty($_POST['ox'])) $mode |= 0001;

    if (@chmod($path . '/' . $file, $mode)) {
        set_message(__('Permissions changed'));
    } else {
        set_message(__('Permissions not changed'), 'error');
    }

    redirect(FM_URL . '?p=' . urlencode($p));
}

/*************************** /ACTIONS ***************************/

// get current path
$path = ROOT_PATH;
if ($p != '') $path .= '/' . $p;

// check path
if (!is_dir($path)) {
    redirect(FM_URL . '?p=');
}

// get parent folder
$parent = get_parent_path($p);

$objects = scandir($path);
$folders = array();
$files = array();
if (is_array($objects)) {
    foreach ($objects as $file) {
        $new_path = $path . '/' . $file;
        if (is_file($new_path)) {
            $files[] = $file;
        } elseif (is_dir($new_path) && $file != '.' && $file != '..') {
            $folders[] = $file;
        }
    }
}

if (!empty($files)) natcasesort($files);
if (!empty($folders)) natcasesort($folders);

// upload form
if (isset($_GET['upload']) && !READONLY) {
    show_header(); // HEADER
    show_navigation_path($p); // current path
    ?>
    <div class="path">
        <p><b><?php _e('Uploading files') ?></b></p>
        <p><?php _e('Destination folder:') ?> <?php echo ROOT_PATH . '/' . $p ?></p>
        <form action="" method="post" enctype="multipart/form-data">
            <input type="hidden" name="p" value="<?php echo encode_html($p) ?>">
            <input type="hidden" name="upl" value="1">
            <input type="file" name="upload[]"><br>
            <input type="file" name="upload[]"><br>
            <input type="file" name="upload[]"><br>
            <input type="file" name="upload[]"><br>
            <input type="file" name="upload[]"><br>
            <br>
            <p>
                <button type="submit" class="btn"><i class="icon-apply"></i> <?php _e('Upload') ?></button> &nbsp;
                <b><a href="?p=<?php echo urlencode($p) ?>"><i class="icon-cancel"></i> <?php _e('Cancel') ?></a></b>
            </p>
        </form>
    </div>
    <?php
    show_footer();
    exit;
}

// copy form POST
if (isset($_POST['copy']) && !READONLY) {
    $copy_files = $_POST['file'];
    if (!is_array($copy_files) || empty($copy_files)) {
        set_message(__('Nothing selected'), 'alert');
        redirect(FM_URL . '?p=' . urlencode($p));
    }

    show_header(); // HEADER
    show_navigation_path($p); // current path
    ?>
    <div class="path">
        <p><b><?php _e('Copying') ?></b></p>
        <form action="" method="post">
            <input type="hidden" name="p" value="<?php echo encode_html($p) ?>">
            <input type="hidden" name="finish" value="1">
            <?php
            foreach ($copy_files as $cf) {
                echo '<input type="hidden" name="file[]" value="' . encode_html($cf) . '">' . PHP_EOL;
            }
            ?>
            <p><?php _e('Files:') ?> <b><?php echo implode('</b>, <b>', $copy_files) ?></b></p>
            <p><?php _e('Source folder:') ?> <?php echo ROOT_PATH ?>/<?php echo $p ?><br>
                <label for="inp_copy_to"><?php _e('Destination folder:') ?></label>
                <?php echo ROOT_PATH ?>/<input type="text" name="copy_to" id="inp_copy_to" value="<?php echo encode_html($p) ?>">
            </p>
            <p><label><input type="checkbox" name="move" value="1"> <?php _e('Move') ?></label></p>
            <p>
                <button type="submit" class="btn"><i class="icon-apply"></i> <?php _e('Copy') ?></button> &nbsp;
                <b><a href="?p=<?php echo urlencode($p) ?>"><i class="icon-cancel"></i> <?php _e('Cancel') ?></a></b>
            </p>
        </form>
    </div>
    <?php
    show_footer();
    exit;
}

// copy form
if (isset($_GET['copy']) && !isset($_GET['finish']) && !READONLY) {
    $copy = $_GET['copy'];
    $copy = clean_path($copy);
    if ($copy == '' || !file_exists(ROOT_PATH . '/' . $copy)) {
        set_message(__('File not found'), 'error');
        redirect(FM_URL . '?p=' . urlencode($p));
    }

    show_header(); // HEADER
    show_navigation_path($p); // current path
    ?>
    <div class="path">
        <p><b><?php _e('Copying') ?></b></p>
        <p>
            <?php _e('Source path:') ?> <?php echo ROOT_PATH ?>/<?php echo $copy ?><br>
            <?php _e('Destination folder:') ?> <?php echo ROOT_PATH ?>/<?php echo $p ?>
        </p>
        <p>
            <b><a href="?p=<?php echo urlencode($p) ?>&amp;copy=<?php echo urlencode($copy) ?>&amp;finish=1"><i class="icon-apply"></i> <?php _e('Copy') ?></a></b> &nbsp;
            <b><a href="?p=<?php echo urlencode($p) ?>&amp;copy=<?php echo urlencode($copy) ?>&amp;finish=1&amp;move=1"><i class="icon-apply"></i> <?php _e('Move') ?></a></b> &nbsp;
            <b><a href="?p=<?php echo urlencode($p) ?>"><i class="icon-cancel"></i> <?php _e('Cancel') ?></a></b>
        </p>
        <p><i><?php _e('Select folder:') ?></i></p>
        <ul class="folders">
            <?php
            if ($parent !== false) {
                ?>
                <li><a href="?p=<?php echo urlencode($parent) ?>&amp;copy=<?php echo urlencode($copy) ?>"><i class="icon-arrow_up"></i> ..</a></li>
            <?php
            }
            foreach ($folders as $f) {
                ?>
                <li><a href="?p=<?php echo urlencode(trim($p . '/' . $f, '/')) ?>&amp;copy=<?php echo urlencode($copy) ?>"><i class="icon-folder"></i> <?php echo $f ?></a></li>
            <?php
            }
            ?>
        </ul>
    </div>
    <?php
    show_footer();
    exit;
}

// zip info
if (isset($_GET['zip'])) {
    $file = $_GET['zip'];
    $file = clean_path($file);
    $file = str_replace('/', '', $file);
    if ($file == '' || !is_file($path . '/' . $file)) {
        set_message(__('File not found'), 'error');
        redirect(FM_URL . '?p=' . urlencode($p));
    }

    show_header(); // HEADER
    show_navigation_path($p); // current path

    $file_url = ROOT_URL . (!empty($p) ? '/' . $p : '') . '/' . $file;
    $file_path = $path . '/' . $file;
    ?>
    <div class="path">
        <p><b><?php _e('Archive') ?> <?php echo $file ?></b></p>
        <?php
        $filenames = get_zif_info($file_path);
        if ($filenames !== false) {
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

            $zip_name = pathinfo($file_path, PATHINFO_FILENAME);
            ?>
            <p>
                <?php _e('Full path:') ?> <?php echo $file_path ?><br>
                <?php _e('File size:') ?> <?php echo get_filesize(filesize($file_path)) ?><br>
                <?php _e('Files in archive:') ?> <?php echo $total_files ?><br>
                <?php _e('Total size:') ?> <?php echo get_filesize($total_uncomp) ?><br>
                <?php _e('Size in archive:') ?> <?php echo get_filesize($total_comp) ?><br>
                <?php _e('Compression:') ?> <?php echo round(($total_comp / $total_uncomp) * 100) ?>%
            </p>
            <p>
                <b><a href="<?php echo $file_url ?>" target="_blank"><i class="icon-folder_open"></i> <?php _e('Open') ?></a></b> &nbsp;
                <?php if (!READONLY): ?>
                    <b><a href="?p=<?php echo urlencode($p) ?>&amp;unzip=<?php echo urlencode($file) ?>"><i class="icon-apply"></i> <?php _e('Unpack') ?></a></b> &nbsp;
                    <b><a href="?p=<?php echo urlencode($p) ?>&amp;unzip=<?php echo urlencode($file) ?>&amp;tofolder=1" title="<?php _e('Unpack to') ?> <?php echo encode_html($zip_name) ?>"><i class="icon-apply"></i>
                        <?php _e('Unpack to folder') ?></a></b> &nbsp;
                <?php endif; ?>
                <b><a href="?p=<?php echo urlencode($p) ?>"><i class="icon-goback"></i> <?php _e('Back') ?></a></b>
            </p>
            <code class="maxheight">
                <?php
                foreach ($filenames as $fn) {
                    if ($fn['folder']) {
                        echo '<b>' . $fn['name'] . '</b><br>';
                    } else {
                        echo $fn['name'] . ' (' . get_filesize($fn['filesize']) . ')<br>';
                    }
                }
                ?>
            </code>
        <?php
        } else {
            ?>
            <p><?php _e('Error while fetching archive info') ?></p>
            <p>
                <b><a href="<?php echo $file_url ?>" target="_blank"><i class="icon-folder_open"></i> <?php _e('Open') ?></a></b> &nbsp;
                <b><a href="?p=<?php echo urlencode($p) ?>"><i class="icon-goback"></i> <?php _e('Back') ?></a></b>
            </p>
        <?php
        }
        ?>
    </div>
    <?php
    show_footer();
    exit;
}

// image info
if (isset($_GET['showimg'])) {
    $file = $_GET['showimg'];
    $file = clean_path($file);
    $file = str_replace('/', '', $file);
    if ($file == '' || !is_file($path . '/' . $file)) {
        set_message(__('File not found'), 'error');
        redirect(FM_URL . '?p=' . urlencode($p));
    }

    show_header(); // HEADER
    show_navigation_path($p); // current path

    $file_url = ROOT_URL . (!empty($p) ? '/' . $p : '') . '/' . $file;
    $file_path = $path . '/' . $file;

    $image_size = getimagesize($file_path);
    ?>
    <div class="path">
        <p><b><?php _e('Image') ?> <?php echo $file ?></b></p>
        <p>
            <?php _e('Full path:') ?> <?php echo $file_path ?><br>
            <?php _e('File size:') ?> <?php echo get_filesize(filesize($file_path)) ?><br>
            <?php _e('MIME-type:') ?> <?php echo isset($image_size['mime']) ? $image_size['mime'] : get_mime_type($file_path) ?><br>
            <?php _e('Image sizes:') ?> <?php echo (isset($image_size[0])) ? $image_size[0] : '0' ?> x <?php echo (isset($image_size[1])) ? $image_size[1] : '0' ?>
        </p>
        <p>
            <b><a href="<?php echo $file_url ?>" target="_blank"><i class="icon-folder_open"></i> <?php _e('Open') ?></a></b> &nbsp;
            <b><a href="?p=<?php echo urlencode($p) ?>"><i class="icon-goback"></i> <?php _e('Back') ?></a></b>
        </p>
        <?php
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (in_array($ext, array('gif', 'jpg', 'jpeg', 'png', 'bmp', 'ico'))) {
            echo '<p><img src="' . $file_url . '" alt="" class="preview-img"></p>';
        }
        ?>
    </div>
    <?php
    show_footer();
    exit;
}

// video & audio info
if (isset($_GET['showvideo']) || isset($_GET['showaudio'])) {
    $is_video = isset($_GET['showvideo']);
    $file = $is_video ? $_GET['showvideo'] : $_GET['showaudio'];
    $file = clean_path($file);
    $file = str_replace('/', '', $file);
    if ($file == '' || !is_file($path . '/' . $file)) {
        set_message(__('File not found'), 'error');
        redirect(FM_URL . '?p=' . urlencode($p));
    }

    show_header(); // HEADER
    show_navigation_path($p); // current path

    $file_url = ROOT_URL . (!empty($p) ? '/' . $p : '') . '/' . $file;
    $file_path = $path . '/' . $file;

    ?>
    <div class="path">
        <p><b><?php $is_video ? _e('Video') : _e('Audio') ?> <?php echo $file ?></b></p>
        <p>
            <?php _e('Full path:') ?> <?php echo $file_path ?><br>
            <?php _e('File size:') ?> <?php echo get_filesize(filesize($file_path)) ?><br>
            <?php _e('MIME-type:') ?> <?php echo get_mime_type($file_path) ?>
        </p>
        <p>
            <b><a href="<?php echo $file_url ?>" target="_blank"><i class="icon-folder_open"></i> <?php _e('Open') ?></a></b> &nbsp;
            <b><a href="?p=<?php echo urlencode($p) ?>"><i class="icon-goback"></i> <?php _e('Back') ?></a></b>
        </p>
        <?php
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (in_array($ext, array('webm', 'mp4', 'm4v', 'ogm', 'ogv'))) {
            echo '<div class="preview-video"><video src="' . $file_url . '" width="640" height="360" controls preload="metadata"></video></div>';
        } elseif (in_array($ext, array('wav', 'mp3', 'ogg'))) {
            echo '<p><audio src="' . $file_url . '" controls preload="metadata"></audio></p>';
        }
        ?>
    </div>
    <?php
    show_footer();
    exit;
}

// txt info
if (isset($_GET['showtxt'])) {
    $file = $_GET['showtxt'];
    $file = clean_path($file);
    $file = str_replace('/', '', $file);
    if ($file == '' || !is_file($path . '/' . $file)) {
        set_message(__('File not found'), 'error');
        redirect(FM_URL . '?p=' . urlencode($p));
    }

    show_header(); // HEADER
    show_navigation_path($p); // current path

    $file_url = ROOT_URL . (!empty($p) ? '/' . $p : '') . '/' . $file;
    $file_path = $path . '/' . $file;

    $content = file_get_contents($file_path);
    $is_utf8 = is_utf8($content);
    if (function_exists('iconv')) {
        if (!$is_utf8) {
            $content = iconv('CP1251', 'UTF-8//IGNORE', $content);
        }
    }

    // php highlight
    $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    if (in_array($ext, array('php', 'php4', 'php5', 'phtml', 'phps'))) {
        $content = highlight_string($content, true);
    } else {
        $content = '<pre>' . encode_html($content) . '</pre>';
    }

    ?>
    <div class="path">
        <p><b><?php _e('File') ?> <?php echo $file ?></b></p>
        <p>
            <?php _e('Full path:') ?> <?php echo $file_path ?><br>
            <?php _e('File size:') ?> <?php echo get_filesize(filesize($file_path)) ?><br>
            <?php _e('MIME-type:') ?> <?php echo get_mime_type($file_path) ?><br>
            <?php _e('Charset:') ?> <?php echo ($is_utf8) ? 'utf-8' : '8 bit' ?>
        </p>
        <p>
            <b><a href="<?php echo $file_url ?>" target="_blank"><i class="icon-folder_open"></i> <?php _e('Open') ?></a></b> &nbsp;
            <b><a href="?p=<?php echo urlencode($p) ?>"><i class="icon-goback"></i> <?php _e('Back') ?></a></b>
        </p>
        <?php echo $content ?>
    </div>
    <?php
    show_footer();
    exit;
}

// chmod
if (isset($_GET['chmod']) && !READONLY) {
    $file = $_GET['chmod'];
    $file = clean_path($file);
    $file = str_replace('/', '', $file);
    if ($file == '' || (!is_file($path . '/' . $file) && !is_dir($path . '/' . $file))) {
        set_message(__('File not found'), 'error');
        redirect(FM_URL . '?p=' . urlencode($p));
    }

    show_header(); // HEADER
    show_navigation_path($p); // current path

    $file_url = ROOT_URL . (!empty($p) ? '/' . $p : '') . '/' . $file;
    $file_path = $path . '/' . $file;

    $mode = fileperms($path . '/' . $file);

    ?>
    <div class="path">
        <p><b><?php _e('Change Permissions') ?></b></p>
        <p>
            <?php _e('Full path:') ?> <?php echo $file_path ?><br>
        </p>
        <form action="" method="post">
            <input type="hidden" name="p" value="<?php echo encode_html($p) ?>">
            <input type="hidden" name="chmod" value="<?php echo encode_html($file) ?>">

            <table class="compact-table">
                <tr>
                    <td></td>
                    <td><b><?php _e('Owner') ?></b></td>
                    <td><b><?php _e('Group') ?></b></td>
                    <td><b><?php _e('Other') ?></b></td>
                </tr>
                <tr>
                    <td style="text-align: right"><b><?php _e('Read') ?></b></td>
                    <td><label><input type="checkbox" name="ur" value="1"<?php if ($mode & 00400) echo ' checked="checked"'; ?>></label></td>
                    <td><label><input type="checkbox" name="gr" value="1"<?php if ($mode & 00040) echo ' checked="checked"'; ?>></label></td>
                    <td><label><input type="checkbox" name="or" value="1"<?php if ($mode & 00004) echo ' checked="checked"'; ?>></label></td>
                </tr>
                <tr>
                    <td style="text-align: right"><b><?php _e('Write') ?></b></td>
                    <td><label><input type="checkbox" name="uw" value="1"<?php if ($mode & 00200) echo ' checked="checked"'; ?>></label></td>
                    <td><label><input type="checkbox" name="gw" value="1"<?php if ($mode & 00020) echo ' checked="checked"'; ?>></label></td>
                    <td><label><input type="checkbox" name="ow" value="1"<?php if ($mode & 00002) echo ' checked="checked"'; ?>></label></td>
                </tr>
                <tr>
                    <td style="text-align: right"><b><?php _e('Execute') ?></b></td>
                    <td><label><input type="checkbox" name="ux" value="1"<?php if ($mode & 00100) echo ' checked="checked"'; ?>></label></td>
                    <td><label><input type="checkbox" name="gx" value="1"<?php if ($mode & 00010) echo ' checked="checked"'; ?>></label></td>
                    <td><label><input type="checkbox" name="ox" value="1"<?php if ($mode & 00001) echo ' checked="checked"'; ?>></label></td>
                </tr>
            </table>

            <p>
                <button type="submit" class="btn"><i class="icon-apply"></i> <?php _e('Change') ?></button> &nbsp;
                <b><a href="?p=<?php echo urlencode($p) ?>"><i class="icon-cancel"></i> <?php _e('Cancel') ?></a></b>
            </p>

        </form>

    </div>
    <?php
    show_footer();
    exit;
}

//--- FILEMANAGER MAIN
show_header(); // HEADER
show_navigation_path($p); // current path

// messages
show_message();

$num_files = count($files);
$num_folders = count($folders);
$all_files_size = 0;
?>
<form action="" method="post">
<input type="hidden" name="p" value="<?php echo encode_html($p) ?>">
<input type="hidden" name="group" value="1">
<table><tr>
<?php if (!READONLY): ?><th style="width:3%"><label><input type="checkbox" title="<?php _e('Invert selection') ?>" onclick="checkbox_toggle()"></label></th><?php endif; ?>
<th><?php _e('Name') ?></th><th style="width:10%"><?php _e('Size') ?></th>
<th style="width:12%"><?php _e('Modified') ?></th><th style="width:6%"><?php _e('Perms') ?></th>
<th style="width:<?php if (!READONLY): ?>13<?php else: ?>6.5<?php endif; ?>%"></th></tr>
<?php
// link to parent folder
if ($parent !== false) {
    ?>
<tr><?php if (!READONLY): ?><td></td><?php endif; ?><td colspan="5"><a href="?p=<?php echo urlencode($parent) ?>"><i class="icon-arrow_up"></i> ..</a></td></tr>
<?php
}
foreach ($folders as $f) {
    $modif = date("d.m.y H:i", filemtime($path . '/' . $f));
    $perms = substr(decoct(fileperms($path . '/' . $f)), -4);
    ?>
<tr>
<?php if (!READONLY): ?><td><label><input type="checkbox" name="file[]" value="<?php echo encode_html($f) ?>"></label></td><?php endif; ?>
<td><a href="?p=<?php echo urlencode(trim($p . '/' . $f, '/')) ?>"><i class="icon-folder"></i> <?php echo $f ?></a></td>
<td><?php _e('Folder') ?></td><td><?php echo $modif ?></td>
<td><?php if (!READONLY): ?><a title="<?php _e('Change Permissions') ?>" href="?p=<?php echo urlencode($p) ?>&amp;chmod=<?php echo urlencode($f) ?>"><?php echo $perms ?></a><?php else: ?><?php echo $perms ?><?php endif; ?></td>
<td><?php if (!READONLY): ?>
<a title="<?php _e('Delete') ?>" href="?p=<?php echo urlencode($p) ?>&amp;del=<?php echo urlencode($f) ?>" onclick="return confirm('<?php _e('Delete folder?') ?>');"><i class="icon-cross"></i></a>
<a title="<?php _e('Rename') ?>" href="#" onclick="rename('<?php echo encode_html($p) ?>', '<?php echo encode_html($f) ?>');return false;"><i class="icon-rename"></i></a>
<a title="<?php _e('Copy to...') ?>" href="?p=&amp;copy=<?php echo urlencode(trim($p . '/' . $f, '/')) ?>"><i class="icon-copy"></i></a>
<?php endif; ?>
<a title="<?php _e('Direct link') ?>" href="<?php echo ROOT_URL . (!empty($p) ? '/' . $p : '') . '/' . $f . '/' ?>" target="_blank"><i class="icon-chain"></i></a>
</td></tr>
    <?php
    flush();
}

foreach ($files as $f) {
    $img = get_file_icon($path . '/' . $f);
    $modif = date("d.m.y H:i", filemtime($path . '/' . $f));
    $filesize_raw = filesize($path . '/' . $f);
    $filesize = get_filesize($filesize_raw);
    $filelink = get_file_link($p, $f);
    $all_files_size += $filesize_raw;
    $perms = substr(decoct(fileperms($path . '/' . $f)), -4);
    ?>
<tr>
<?php if (!READONLY): ?><td><label><input type="checkbox" name="file[]" value="<?php echo encode_html($f) ?>"></label></td><?php endif; ?>
<td><?php if (!empty($filelink)) echo '<a href="' . $filelink . '" title="' . __('File info') . '">' ?><i class="<?php echo $img ?>"></i> <?php echo $f ?><?php if (!empty($filelink)) echo '</a>' ?></td>
<td><span title="<?php printf(__('%s byte'), $filesize_raw) ?>"><?php echo $filesize ?></span></td>
<td><?php echo $modif ?></td>
<td><?php if (!READONLY): ?><a title="<?php _e('Change Permissions') ?>" href="?p=<?php echo urlencode($p) ?>&amp;chmod=<?php echo urlencode($f) ?>"><?php echo $perms ?></a><?php else: ?><?php echo $perms ?><?php endif; ?></td>
<td>
<?php if (!READONLY): ?>
<a title="<?php _e('Delete') ?>" href="?p=<?php echo urlencode($p) ?>&amp;del=<?php echo urlencode($f) ?>" onclick="return confirm('<?php _e('Delete file?') ?>');"><i class="icon-cross"></i></a>
<a title="<?php _e('Rename') ?>" href="#" onclick="rename('<?php echo encode_html($p) ?>', '<?php echo encode_html($f) ?>');return false;"><i class="icon-rename"></i></a>
<a title="<?php _e('Copy to...') ?>" href="?p=<?php echo urlencode($p) ?>&amp;copy=<?php echo urlencode(trim($p . '/' . $f, '/')) ?>"><i class="icon-copy"></i></a>
<?php endif; ?>
<a title="<?php _e('Direct link') ?>" href="<?php echo ROOT_URL . (!empty($p) ? '/' . $p : '') . '/' . $f ?>" target="_blank"><i class="icon-chain"></i></a>
<a title="<?php _e('Download') ?>" href="?p=<?php echo urlencode($p) ?>&amp;dl=<?php echo urlencode($f) ?>"><i class="icon-download"></i></a>
</td></tr>
    <?php
    flush();
}

if (empty($folders) && empty($files)) {
    ?>
<tr><?php if (!READONLY): ?><td></td><?php endif; ?><td colspan="5"><em><?php _e('Folder is empty') ?></em></td></tr>
<?php
} else {
    ?>
<tr><?php if (!READONLY): ?><td class="gray"></td><?php endif; ?><td class="gray" colspan="5">
<?php _e('Full size:') ?> <span title="<?php printf(__('%s byte'), $all_files_size) ?>"><?php echo get_filesize($all_files_size) ?></span>,
<?php _e('files:') ?> <?php echo $num_files ?>,
<?php _e('folders:') ?> <?php echo $num_folders ?>
</td></tr>
<?php
}
?>
</table>
<?php if (!READONLY): ?>
<p class="path"><a href="#" onclick="select_all();return false;"><i class="icon-checkbox"></i> <?php _e('Select all') ?></a> &nbsp;
<a href="#" onclick="unselect_all();return false;"><i class="icon-checkbox_uncheck"></i> <?php _e('Unselect all') ?></a> &nbsp;
<a href="#" onclick="invert_all();return false;"><i class="icon-checkbox_invert"></i> <?php _e('Invert selection') ?></a></p>
<p><input type="submit" name="delete" value="<?php _e('Delete') ?>" onclick="return confirm('<?php _e('Delete selected files and folders?') ?>')">
<input type="submit" name="zip" value="<?php _e('Pack') ?>">
<input type="submit" name="copy" value="<?php _e('Copy') ?>"></p>
<?php endif; ?>
</form>

<?php
show_footer();

//--- END

// Functions

//--- files

/*
 * Delete folder (recursively) or file
 */
function rdelete($path)
{
    if (is_dir($path)) {
        $objects = scandir($path);
        $ok = true;
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (!rdelete($path . '/' . $file)) {
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

/*
 * Recursive chmod
 */
function rchmod($path, $filemode, $dirmode)
{
    if (is_dir($path)) {
        if (!chmod($path, $dirmode)) {
            return false;
        }
        $objects = scandir($path);
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (!rchmod($path . '/' . $file, $filemode, $dirmode)) return false;
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

/*
 * Safely rename
 */
function rename_safe($old, $new)
{
    return (!file_exists($new) && file_exists($old)) ? rename($old, $new) : null;
}

/*
 * Copy file or folder recursively.
 * $upd - update files
 * $force - create folder with same names instead file
 */
function rcopy($path, $dest, $upd = true, $force = true)
{
    if (is_dir($path)) {
        if (!mkdir_safe($dest, $force)) return false;
        $objects = scandir($path);
        $ok = true;
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if ($file != '.' && $file != '..') {
                    if (!rcopy($path . '/' . $file, $dest . '/' . $file)) {
                        $ok = false;
                    }
                }
            }
        }
        return $ok;
    } elseif (is_file($path)) {
        return copy_safe($path, $dest, $upd);
    }
    return false;
}

/*
 * Safely create folder
 */
function mkdir_safe($dir, $force)
{
    if (file_exists($dir)) {
        if (is_dir($dir)) return $dir;
        else if (!$force) return false;
        unlink($dir);
    }
    return mkdir($dir, 0777, true);
}

/*
 * Safely copy file
 */
function copy_safe($f1, $f2, $upd)
{
    $time1 = filemtime($f1);
    if (file_exists($f2)) {
        $time2 = filemtime($f2);
        if ($time2 >= $time1 && $upd) return false;
    }
    $ok = copy($f1, $f2);
    if ($ok) touch($f2, $time1);
    return $ok;
}


//--- functions

// get mime type
function get_mime_type($file_path)
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

// function to parse the http auth header
function http_digest_parse($txt)
{
    // protect against missing data
    $needed_parts = array('nonce' => 1, 'nc' => 1, 'cnonce' => 1, 'qop' => 1, 'username' => 1, 'uri' => 1, 'response' => 1);
    $data = array();
    $keys = implode('|', array_keys($needed_parts));

    preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);

    foreach ($matches as $m) {
        $data[$m[1]] = $m[3] ? $m[3] : $m[4];
        unset($needed_parts[$m[1]]);
    }

    return $needed_parts ? false : $data;
}

function redirect($url, $code = 302)
{
    header('Location: ' . $url, true, $code);
    exit;
}

function clean_path($path)
{
    $path = trim($path);
    $path = trim($path, '\\/');
    $path = str_replace(array('../', '..\\'), '', $path);
    if ($path == '..') $path = '';
    return str_replace('\\', '/', $path);
}

function get_parent_path($path)
{
    $path = clean_path($path);
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

function get_filesize($size)
{
    if ($size < 1000) return sprintf(__('%s byte'), $size);
    elseif (($size / 1024) < 1000) return sprintf(__('%s KB'), round(($size / 1024), 1));
    elseif (($size / 1024 / 1024) < 1000) return sprintf(__('%s MB'), round(($size / 1024 / 1024), 1));
    else return sprintf(__('%s GB'), round(($size / 1024 / 1024 / 1024), 1));
}

function get_zif_info($path)
{
    if (function_exists('zip_open')) {
        $arch = zip_open($path);
        if ($arch) {
            $filenames = array();
            while ($zip_entry = zip_read($arch)) {
                $zip_name = zip_entry_name($zip_entry);
                $zip_folder = (substr($zip_name, -1) == '/') ? true : false;
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

function get_files_recursive($path = '.')
{
    $files = array();
    if (is_dir($path)) {
        $objects = scandir($path);
        if (is_array($objects)) {
            foreach ($objects as $file) {
                if (is_file($path . '/' . $file)) {
                    $files[] = $path . '/' . $file;
                } elseif (is_dir($path . '/' . $file) && $file != '.' && $file != '..') {
                    $files = array_merge($files, get_files_recursive($path . '/' . $file));
                }
            }
        }
    }
    return $files;
}

function encode_html($text)
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function set_message($msg, $status = 'ok')
{
    $_SESSION['message'] = $msg;
    $_SESSION['status'] = $status;
}

function is_utf8($string)
{
    return preg_match('//u', $string);
}

// translation
function __($str)
{
    global $lang;

    if (!isset($lang)) return $str;

    $strings = get_strings($lang);
    if (!$strings) return $str;
    $strings = (array)$strings;

    if (array_key_exists($str, $strings)) {
        return $strings[$str];
    }
    return $str;
}

// echo translation
function _e($str)
{
    echo __($str);
}

function get_file_link($p, $f)
{
    $link = '';

    $path = ROOT_PATH;
    if ($p != '') $path .= '/' . $p;

    if (!empty($f)) {
        $path .= '/' . $f;
        // get extension
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        switch ($ext) {
            case 'zip':
                $link = '?p=' . urlencode($p) . '&amp;zip=' . urlencode($f); break;
            case 'ico': case 'gif': case 'jpg': case 'jpeg': case 'jpc': case 'jp2': case 'jpx':
            case 'xbm': case 'wbmp': case 'png': case 'bmp': case 'tif': case 'tiff': case 'psd':
                $link = '?p=' . urlencode($p) . '&amp;showimg=' . urlencode($f); break;
            case 'webm': case 'mp4': case 'm4v': case 'ogm': case 'ogv':
                $link = '?p=' . urlencode($p) . '&amp;showvideo=' . urlencode($f); break;
            case 'wav': case 'mp3': case 'ogg':
                $link = '?p=' . urlencode($p) . '&amp;showaudio=' . urlencode($f); break;
            case 'txt': case 'css': case 'ini': case 'conf': case 'log': case 'htaccess':
            case 'passwd': case 'ftpquota': case 'sql': case 'js': case 'json': case 'sh':
            case 'config': case 'php': case 'php4': case 'php5': case 'phps': case 'phtml':
            case 'htm': case 'html': case 'shtml': case 'xhtml': case 'xml': case 'xsl':
            case 'm3u': case 'm3u8': case 'pls': case 'cue': case 'eml': case 'msg':
            case 'csv': case 'bat': case 'twig': case 'tpl': case 'md': case 'gitignore':
            case 'less': case 'sass':
                $link = '?p=' . urlencode($p) . '&amp;showtxt=' . urlencode($f); break;
            default:
                $link = '';
        }
    }

    return $link;
}

function get_file_icon($path)
{
    // get extension
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

    switch ($ext) {
        case 'ico': case 'gif': case 'jpg': case 'jpeg': case 'jpc': case 'jp2':
        case 'jpx': case 'xbm': case 'wbmp': case 'png': case 'bmp': case 'tif':
        case 'tiff':
            $img = 'icon-file_image'; break;
        case 'txt': case 'css': case 'ini': case 'conf': case 'log': case 'htaccess':
        case 'passwd': case 'ftpquota': case 'sql': case 'js': case 'json': case 'sh':
        case 'config': case 'twig': case 'tpl': case 'md': case 'gitignore':
        case 'less': case 'sass':
            $img = 'icon-file_text'; break;
        case 'zip': case 'rar': case 'gz': case 'tar': case '7z':
            $img = 'icon-file_zip'; break;
        case 'php': case 'php4': case 'php5': case 'phps': case 'phtml':
            $img = 'icon-file_php'; break;
        case 'htm': case 'html': case 'shtml': case 'xhtml':
            $img = 'icon-file_html'; break;
        case 'xml': case 'xsl':
            $img = 'icon-file_code'; break;
        case 'wav': case 'mp3': case 'mp2': case 'm4a': case 'aac': case 'ogg':
        case 'oga': case 'wma': case 'mka': case 'flac': case 'ac3': case 'tds':
            $img = 'icon-file_music'; break;
        case 'm3u': case 'm3u8': case 'pls': case 'cue':
            $img = 'icon-file_playlist'; break;
        case 'avi': case 'mpg': case 'mpeg': case 'mp4': case 'm4v': case 'flv':
        case 'f4v': case 'ogm': case 'ogv': case 'mov': case 'mkv': case '3gp':
        case 'asf': case 'wmv':
            $img = 'icon-file_film'; break;
        case 'eml': case 'msg':
            $img = 'icon-file_outlook'; break;
        case 'xls': case 'xlsx':
            $img = 'icon-file_excel'; break;
        case 'csv':
            $img = 'icon-file_csv'; break;
        case 'doc': case 'docx':
            $img = 'icon-file_word'; break;
        case 'ppt': case 'pptx':
            $img = 'icon-file_powerpoint'; break;
        case 'ttf': case 'ttc': case 'otf': case 'woff': case 'eot': case 'fon':
            $img = 'icon-file_font'; break;
        case 'pdf':
            $img = 'icon-file_pdf'; break;
        case 'psd':
            $img = 'icon-file_photoshop'; break;
        case 'ai': case 'eps':
            $img = 'icon-file_illustrator'; break;
        case 'fla':
            $img = 'icon-file_flash'; break;
        case 'swf':
            $img = 'icon-file_swf'; break;
        case 'exe': case 'msi':
            $img = 'icon-file_application'; break;
        case 'bat':
            $img = 'icon-file_terminal'; break;
        default:
            $img = 'icon-document';
    }

    return $img;
}

/**
 * Class to work with zip files (using ZipArchive)
 */
class Zipper
{
    private $zip;

    public function __construct()
    {
        $this->zip = new ZipArchive;
    }

    /*
     * Create archive with name $filename and files $files (RELATIVE PATHS!)
     */
    public function create($filename, $files)
    {
        $res = $this->zip->open($filename, ZipArchive::CREATE);
        if ($res !== true) return false;
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

    /*
     * Extract archive $filename to folder $path (RELATIVE OR ABSOLUTE PATHS)
     */
    public function unzip($filename, $path)
    {
        $res = $this->zip->open($filename);
        if ($res !== true) return false;
        if ($this->zip->extractTo($path)) {
            $this->zip->close();
            return true;
        }
        return false;
    }

    /*
     * Add file/folder to archive
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

    /*
     * Add folder recursively
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

function show_navigation_path($path)
{
    global $p, $use_auth, $auth_users;
    ?>
<div class="path">
<div class='float-right'>
<?php if (!READONLY): ?>
<a title="<?php _e('Upload files') ?>" href="?p=<?php echo urlencode($p) ?>&amp;upload"><i class="icon-upload"></i></a>
<a title="<?php _e('New folder') ?>" href="#" onclick="newfolder('<?php echo encode_html($p) ?>');return false;"><i class="icon-folder_add"></i></a>
<?php endif; ?>
<?php if ($use_auth && !empty($auth_users)): ?><a title="<?php _e('Logout') ?>" href="?logout=1"><i class="icon-logout"></i></a><?php endif; ?>
</div>
        <?php
        $path = clean_path($path);
        $root_url = "<a href='?p='><i class='icon-home' title='" . ROOT_PATH . "'></i></a>";
        $sep = '<i class="icon-separator"></i>';
        if ($path != '') {
            $exploded = explode('/', $path);
            $count = count($exploded);
            $array = array();
            $parent = '';
            for ($i = 0; $i < $count; $i++) {
                $parent = trim($parent . '/' . $exploded[$i], '/');
                $parent_enc = urlencode($parent);
                $array[] = "<a href='?p={$parent_enc}'>{$exploded[$i]}</a>";
            }
            $root_url .= $sep . implode($sep, $array);
        }
        echo $root_url;
        ?>
</div>
<?php
}

// show session message
function show_message()
{
    if (isset($_SESSION['message'])) {
        $class = isset($_SESSION['status']) ? $_SESSION['status'] : 'ok';
        echo '<p class="message ' . $class . '">' . $_SESSION['message'] . '</p>';
        unset($_SESSION['message']);
        unset($_SESSION['status']);
    }
}

/*
 * Header
 */
function show_header()
{
    $sprites_ver = '20150326';
    header("Content-Type: text/html; charset=utf-8");
    ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>File Manager</title>
<style>
html,body,div,span,p,pre,a,code,em,img,small,strong,ol,ul,li,form,label,table,tr,th,td{margin:0;padding:0;vertical-align:baseline;outline:none;font-size:100%;background:transparent;border:none;text-decoration:none}
html{overflow-y:scroll}body{padding:0;font:13px/16px Arial,sans-serif;color:#222;background:#efefef}
input,select,textarea,button{font-size:inherit;font-family:inherit}
a{color:#296ea3;text-decoration:none}a:hover{color:#b00}img{vertical-align:middle;border:none}
a img{border:none}span{color:#777}small{font-size:11px;color:#999}p{margin-bottom:10px}
ul{margin-left:2em;margin-bottom:10px}ul{list-style-type:none;margin-left:0}ul li{padding:3px 0}
table{border-collapse:collapse;border-spacing:0;margin-bottom:10px;width:100%}
th,td{padding:4px 7px;text-align:left;vertical-align:top;border:1px solid #ddd;background:#fff;white-space:nowrap}
th,td.gray{background-color:#eee}td.gray span{color:#222}tr:hover td{background-color:#f5f5f5}tr:hover td.gray{background-color:#eee}
code,pre{display:block;margin-bottom:10px;font:13px/16px Consolas,'Courier New',Courier,monospace;border:1px dashed #ccc;padding:5px;overflow:auto}
code.maxheight,pre.maxheight{max-height:512px}input[type="checkbox"]{margin:0;padding:0}
#wrapper{max-width:900px;min-width:400px;margin:10px auto}
.path{padding:4px 7px;border:1px solid #ddd;background-color:#fff;margin-bottom:10px}
.right{text-align:right}.center{text-align:center}.float-right{float:right}
.message{padding:4px 7px;border:1px solid #ddd;background-color:#fff}
.message.ok{border-color:green;color:green}.message.error{border-color:red;color:red}.message.alert{border-color:orange;color:orange}
.btn{border:0;background:none;padding:0;margin:0;font-weight:bold;color:#296ea3;cursor:pointer}.btn:hover{color:#b00}
.preview-img{max-width:100%;background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAKklEQVR42mL5//8/Azbw+PFjrOJMDCSCUQ3EABZc4S0rKzsaSvTTABBgAMyfCMsY4B9iAAAAAElFTkSuQmCC") repeat 0 0}
.preview-video{position:relative;max-width:100%;height:0;padding-bottom:62.5%;margin-bottom:10px}.preview-video video{position:absolute;width:100%;height:100%;left:0;top:0;background:#000}
[class*="icon-"]{display:inline-block;width:16px;height:16px;background:url("<?php echo FM_URL ?>?img=sprites&amp;t=<?php echo $sprites_ver ?>") no-repeat 0 0;vertical-align:bottom}
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
.compact-table{border:0;width:auto}.compact-table td,.compact-table th{width:100px;border:0;text-align:center}.compact-table tr:hover td{background-color:#fff}
</style>
<link rel="icon" href="<?php echo FM_URL ?>?img=favicon" type="image/png">
<link rel="shortcut icon" href="<?php echo FM_URL ?>?img=favicon" type="image/png">
</head>
<body>
<div id="wrapper">
<?php
}

/*
 * Footer
 */
function show_footer()
{
    ?>
<p class="center"><small><a href="https://github.com/alexantr/filemanager" target="_blank">PHP File Manager</a></small></p>
</div>
<script>
function newfolder(p){var n=prompt('<?php _e('New folder name') ?>','folder');if(n!==null&&n!==''){window.location.search='p='+encodeURIComponent(p)+'&new='+encodeURIComponent(n);}}
function rename(p,f){var n=prompt('<?php _e('New name') ?>',f);if(n!==null&&n!==''&&n!=f){window.location.search='p='+encodeURIComponent(p)+'&ren='+encodeURIComponent(f)+'&to='+encodeURIComponent(n);}}
function change_checkboxes(l,v){for(var i=l.length-1;i>=0;i--){l[i].checked=(typeof v==='boolean')?v:!l[i].checked;}}
function get_checkboxes(){var i=document.getElementsByName('file[]'),a=[];for(var j=i.length-1;j>=0;j--){if(i[j].type='checkbox'){a.push(i[j]);}}return a;}
function select_all(){var l=get_checkboxes();change_checkboxes(l,true);}
function unselect_all(){var l=get_checkboxes();change_checkboxes(l,false);}
function invert_all(){var l=get_checkboxes();change_checkboxes(l);}
function checkbox_toggle(){var l=get_checkboxes();l.push(this);change_checkboxes(l);}
</script>
</body>
</html>
<?php
}

/*
 * Show Image
 */
function show_image()
{
    if (isset($_GET['img'])) {
        $modified_time = gmdate('D, d M Y 00:00:00') . ' GMT';
        $expires_time = gmdate('D, d M Y 00:00:00', strtotime('+1 day')) . ' GMT';

        $images = get_images_array();
        $img = trim($_GET['img']);
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
}

/*
 * Encoded images
 */
function get_images_array()
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
        'sprites' => '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
WyT+DbQcaQClS8UbAAAAAElFTkSuQmCC',
    );
}

function get_strings($lang)
{
    $strings['ru'] = array(
        'Folder <b>%s</b> deleted' => 'Папка <b>%s</b> удалена',
        'Folder <b>%s</b> not deleted' => 'Папка <b>%s</b> не удалена',
        'File <b>%s</b> deleted' => 'Файл <b>%s</b> удален',
        'File <b>%s</b> not deleted' => 'Файл <b>%s</b> не удален',
        'Wrong file or folder name' => 'Имя папки или файла задано не верно',
        'Folder <b>%s</b> created' => 'Папка <b>%s</b> создана',
        'Folder <b>%s</b> already exists' => 'Папка <b>%s</b> уже существует',
        'Folder <b>%s</b> not created' => 'Папка <b>%s</b> не создана',
        'Wrong folder name' => 'Имя папки задано не верно',
        'Source path not defined' => 'Не задан исходный путь',
        'Moved from <b>%s</b> to <b>%s</b>' => 'Перемещено из <b>%s</b> в <b>%s</b>',
        'File or folder with this path already exists' => 'Файл или папка уже есть по указанному пути',
        'Error while moving from <b>%s</b> to <b>%s</b>' => 'Произошла ошибка при перемещении из <b>%s</b> в <b>%s</b>',
        'Copyied from <b>%s</b> to <b>%s</b>' => 'Скопировано из <b>%s</b> в <b>%s</b>',
        'Error while copying from <b>%s</b> to <b>%s</b>' => 'Произошла ошибка при копировании из <b>%s</b> в <b>%s</b>',
        'Paths must be not equal' => 'Пути не должны совпадать',
        'Unable to create destination folder' => 'Невозможно создать папку назначения',
        'Selected files and folders moved' => 'Все отмеченные файлы и папки перемещены',
        'Selected files and folders copied' => 'Все отмеченные файлы и папки сопированы',
        'Error while moving items' => 'При перемещении возникли ошибки',
        'Error while copying items' => 'При копировании возникли ошибки',
        'Nothing selected' => 'Ничего не выбрано',
        'Renamed from <b>%s</b> to <b>%s</b>' => 'Переименовано из <b>%s</b> в <b>%s</b>',
        'Error while renaming from <b>%s</b> to <b>%s</b>' => 'Произошла ошибка при переименовании из <b>%s</b> в <b>%s</b>',
        'Names not set' => 'Не заданы имена',
        'File not found' => 'Файл не найден',
        'All files uploaded to <b>%s</b>' => 'Все файлы загружены в папку <b>%s</b>',
        'Nothing uploaded' => 'Ничего не загружено',
        'Error while uploading files. Uploaded files: %s' => 'При загрузке файлов возникли ошибки. Загружено файлов: %s',
        'Selected files and folder deleted' => 'Все отмеченные файлы и папки удалены',
        'Error while deleting items' => 'При удалении возникли ошибки',
        'Archive <b>%s</b> created' => 'Архив <b>%s</b> успешно создан',
        'Archive not created' => 'Ошибка. Архив не создан',
        'Archive unpacked' => 'Архив распакован',
        'Archive not unpacked' => 'Архив не распакован',
        'Uploading files' => 'Загрузка файлов',
        'Destination folder:' => 'Папка назначения:',
        'Upload' => 'Загрузить',
        'Cancel' => 'Отмена',
        'Copying' => 'Копирование',
        'Files:' => 'Файлы:',
        'Source folder:' => 'Исходная папка:',
        'Move' => 'Переместить',
        'Select folder:' => 'Выбрать папку:',
        'Source path:' => 'Исходный путь:',
        'Archive' => 'Архив',
        'Full path:' => 'Полный путь:',
        'File size:' => 'Размер файла:',
        'Files in archive:' => 'Файлов в архиве:',
        'Total size:' => 'Общий размер:',
        'Size in archive:' => 'Размер в архиве:',
        'Compression:' => 'Степень сжатия:',
        'Open' => 'Открыть',
        'Unpack' => 'Распаковать',
        'Unpack to' => 'Распаковать в',
        'Unpack to folder' => 'Распаковать в папку',
        'Back' => 'Назад',
        'Error while fetching archive info' => 'Ошибка получения информации об архиве',
        'Image' => 'Изображение',
        'MIME-type:' => 'MIME-тип:',
        'Image sizes:' => 'Размеры изображения:',
        'File' => 'Файл',
        'Charset:' => 'Кодировка:',
        'Name' => 'Имя',
        'Size' => 'Размер',
        'Modified' => 'Изменен',
        'Folder' => 'Папка',
        'Delete' => 'Удалить',
        'Delete folder?' => 'Удалить папку?',
        'Delete file?' => 'Удалить файл?',
        'Rename' => 'Переименовать',
        'Copy to...' => 'Копировать в...',
        'File info' => 'Информация о файле',
        '%s byte' => '%s байт',
        '%s KB' => '%s КБ',
        '%s MB' => '%s МБ',
        '%s GB' => '%s ГБ',
        'Download' => 'Скачать',
        'Folder is empty' => 'Папка пуста',
        'Select all' => 'Выделить все',
        'Unselect all' => 'Снять выделение',
        'Invert selection' => 'Инвертировать выделение',
        'Delete selected files and folders?' => 'Удалить выбранные файлы и папки?',
        'Pack' => 'Упаковать',
        'Copy' => 'Копировать',
        'Upload files' => 'Загрузить файлы',
        'New folder' => 'Новая папка',
        'New folder name' => 'Имя новой папки',
        'New name' => 'Новое имя',
        'Operations with archives are not available' => 'Операции с архивами недоступны',
        'Full size:' => 'Общий размер:',
        'files:' => 'файлов:',
        'folders:' => 'папок:',
        'Perms' => 'Права',
        'Username' => 'Имя пользователя',
        'Password' => 'Пароль',
        'Login' => 'Войти',
        'Logout' => 'Выход',
        'Wrong password' => 'Неверный пароль',
        'You are logged in' => 'Вы успешно вошли',
        'Change Permissions' => 'Изменение прав доступа',
        'Permissions:' => 'Права доступа:',
        'Change' => 'Изменить',
        'Owner' => 'Владелец',
        'Group' => 'Группа',
        'Other' => 'Прочие',
        'Read' => 'Чтение',
        'Write' => 'Запись',
        'Execute' => 'Выполнение',
        'Permissions changed' => 'Права изменены',
        'Permissions not changed' => 'Права не изменены',
        'Video' => 'Видео',
        'Audio' => 'Аудио',
        'Direct link' => 'Прямая ссылка',
    );
    $strings['fr'] = array(
        'Folder <b>%s</b> deleted' => 'Dossier <b>%s</b> supprimé',
        'Folder <b>%s</b> not deleted' => 'Dossier <b>%s</b> non supprimé',
        'File <b>%s</b> deleted' => 'Fichier <b>%s</b> supprimé',
        'File <b>%s</b> not deleted' => 'Fichier <b>%s</b> non supprimé',
        'Wrong file or folder name' => 'Nom de fichier ou dossier incorrect',
        'Folder <b>%s</b> created' => 'Dossier <b>%s</b> créé',
        'Folder <b>%s</b> already exists' => 'Dossier <b>%s</b> déjà existant',
        'Folder <b>%s</b> not created' => 'Dossier <b>%s</b> non créé',
        'Wrong folder name' => 'Nom de dossier inccorect',
        'Source path not defined' => 'Chemin source non défini',
        'Moved from <b>%s</b> to <b>%s</b>' => 'Déplacé de <b>%s</b> à <b>%s</b>',
        'File or folder with this path already exists' => 'Fichier ou dossier avec ce chemin déjà existant',
        'Error while moving from <b>%s</b> to <b>%s</b>' => 'Erreur lors du déplacement de <b>%s</b> à <b>%s</b>',
        'Copyied from <b>%s</b> to <b>%s</b>' => 'Copié de <b>%s</b> à <b>%s</b>',
        'Error while copying from <b>%s</b> to <b>%s</b>' => 'Erreur lors de la copie de <b>%s</b> à <b>%s</b>',
        'Paths must be not equal' => 'Les chemins doivent être différents',
        'Unable to create destination folder' => 'Impossible de créer le dossier de destination',
        'Selected files and folders moved' => 'Fichiers et dossiers sélectionnés déplacés',
        'Selected files and folders copied' => 'Fichiers et dossiers sélectionnés copiés',
        'Error while moving items' => 'Erreur lors du déplacement des éléments',
        'Error while copying items' => 'Erreur lors de la copie des éléments',
        'Nothing selected' => 'Sélection vide',
        'Renamed from <b>%s</b> to <b>%s</b>' => 'Renommé de <b>%s</b> à <b>%s</b>',
        'Error while renaming from <b>%s</b> to <b>%s</b>' => 'Erreur lors du renommage de <b>%s</b> en <b>%s</b>',
        'Names not set' => 'Noms indéfinis',
        'File not found' => 'Fichier non trouvé',
        'All files uploaded to <b>%s</b>' => 'Tous les fichiers ont été envoyé dans <b>%s</b>',
        'Nothing uploaded' => 'Rien a été envoyé',
        'Error while uploading files. Uploaded files: %s' => 'Erreur lors de l\'envoi des fichiers. Fichiers envoyés : %s',
        'Selected files and folder deleted' => 'Fichiers et dossier sélectionnés supprimés',
        'Error while deleting items' => 'Erreur lors de la suppression des éléments',
        'Archive <b>%s</b> created' => 'Archive <b>%s</b> créée',
        'Archive not created' => 'Archive non créée',
        'Archive unpacked' => 'Archive décompressée',
        'Archive not unpacked' => 'Archive non décompressée',
        'Uploading files' => 'Envoie des fichiers',
        'Destination folder:' => 'Dossier de destination :',
        'Upload' => 'Envoi',
        'Cancel' => 'Annuler',
        'Copying' => 'Copie en cours',
        'Files:' => 'Fichiers :',
        'Source folder:' => 'Dossier source :',
        'Move' => 'Déplacer',
        'Select folder:' => 'Dossier sélectionné :',
        'Source path:' => 'Chemin source :',
        'Archive' => 'Archive',
        'Full path:' => 'Chemin complet :',
        'File size:' => 'Taille du fichier :',
        'Files in archive:' => 'Fichiers dans l\'archive :',
        'Total size:' => 'Taille totale :',
        'Size in archive:' => 'Taille dans l\'archive :',
        'Compression:' => 'Compression :',
        'Open' => 'Ouvrir',
        'Unpack' => 'Décompresser',
        'Unpack to' => 'Décompresser vers',
        'Unpack to folder' => 'Décompresser vers le dossier',
        'Back' => 'Retour',
        'Error while fetching archive info' => 'Erreur lors de la récupération des informations de l\'archive',
        'Image' => 'Image',
        'MIME-type:' => 'MIME-Type :',
        'Image sizes:' => 'Taille de l\'image :',
        'File' => 'Fichier',
        'Charset:' => 'Charset :',
        'Name' => 'Nom',
        'Size' => 'Taille',
        'Modified' => 'Modifié',
        'Folder' => 'Dossier',
        'Delete' => 'Supprimer',
        'Delete folder?' => 'Supprimer le dossier ?',
        'Delete file?' => 'Supprimer le fichier ?',
        'Rename' => 'Renommer',
        'Copy to...' => 'Copier vers...',
        'File info' => 'Informations',
        '%s byte' => '%s octet',
        '%s KB' => '%s Кb',
        '%s MB' => '%s Мb',
        '%s GB' => '%s Gb',
        'Download' => 'Télécharger',
        'Folder is empty' => 'Dossier vide',
        'Select all' => 'Tout sélectionner',
        'Unselect all' => 'Tout désélectionner',
        'Invert selection' => 'Inverser la sélection',
        'Delete selected files and folders?' => 'Supprimer les fichiers et dossiers sélectionnés ?',
        'Pack' => 'Archiver',
        'Copy' => 'Copier',
        'Upload files' => 'Envoyer des fichiers',
        'New folder' => 'Nouveau dossier',
        'New folder name' => 'Nouveau nom de dossier',
        'New name' => 'Nouveau nom',
        'Operations with archives are not available' => 'Opérations d\archivage non disponibles',
        'Full size:' => 'Taille totale :',
        'files:' => 'fichiers :',
        'folders:' => 'dossiers :',
        'Perms' => 'Permissions',
        'Username' => 'Nom d\'utilisateur',
        'Password' => 'Mot de passe',
        'Login' => 'Identifiant',
        'Logout' => 'Déconnexion',
        'Wrong password' => 'Mauvais mot de passe',
        'You are logged in' => 'Vous êtes connecté',
        'Change Permissions' => 'Modifier les permissions',
        'Permissions:' => 'Permissions:',
        'Change' => 'Modifier',
        'Owner' => 'Propriétaire',
        'Group' => 'Groupe',
        'Other' => 'Autre',
        'Read' => 'Lire',
        'Write' => 'Écrire',
        'Execute' => 'Exécuter',
        'Permissions changed' => 'Permissions modifiées',
        'Permissions not changed' => 'Permission non modifiées',
        'Video' => 'Vidéo',
        'Audio' => 'Audio',
        'Direct link' => 'Lien direct',
    );
    if (isset($strings[$lang])) {
        return $strings[$lang];
    } else {
        return false;
    }
}
