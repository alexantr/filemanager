<?php
/**
 * PHP File Manager
 * Author: Alex Yashkin <alex.yashkin@gmail.com>
 */

### CONFIG

// Use Auth (set true/false to enable/disable it)
$use_auth = false;

// Users array('Username' => 'Password', 'Username2' => 'Password2', ...)
$auth_users = array(
	'admin' => 'admin',
);

// Default timezone for date() and time()
$default_timezone = 'Europe/Minsk'; // UTC+3

// Language (en, ru, fr)
$lang = 'ru';

### END CONFIG

error_reporting(E_ALL);
set_time_limit(600);

date_default_timezone_set($default_timezone);

ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
if (function_exists('mb_regex_encoding')) mb_regex_encoding('UTF-8');

session_cache_limiter('');
session_name('filemanager');
session_start();

define('DS', '/');

// abs path for site
define('ABSPATH', $_SERVER['DOCUMENT_ROOT']);

define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);

// logout
if (isset($_GET['logout'])) {
	unset($_SESSION['logged']);
	redirect(BASE_URL);
}

// Show image here
show_image();

// Auth
if ($use_auth && !empty($auth_users)) {
	// Logged
	if (isset($_SESSION['logged']) && $_SESSION['logged'] === true) {
		$_SESSION['logged'] = true;
	}
	// Logging In
	elseif (isset($_POST['fm_usr']) && isset($_POST['fm_pwd'])) {
		sleep(1);
		if (isset($auth_users[$_POST['fm_usr']]) && $_POST['fm_pwd'] === $auth_users[$_POST['fm_usr']]) {
			$_SESSION['logged'] = true;
			set_message(__('You are logged in'));
			redirect(BASE_URL . '?p=');
		}
		else {
			$_SESSION['logged'] = false;
			set_message(__('Wrong password'), 'error');
			redirect(BASE_URL);
		}
	}
	// Form
	else {
		$_SESSION['logged'] = false;
		show_header();
		show_message();
		?>
		<div class="path">
			<form action="" method="post" style="margin:10px;text-align:center">
				<input type="text" name="fm_usr" value="" placeholder="<?php _e('Username') ?>" required>
				<input type="password" name="fm_pwd" value="" placeholder="<?php _e('Password') ?>" required>
				<input type="submit" value="<?php _e('Login') ?>">
			</form>
		</div>
		<?php
		show_footer();
		exit;
	}
}

// always use ?p=
if (!isset($_GET['p'])) redirect(BASE_URL . '?p=');

// get path
$p = isset($_GET['p']) ? $_GET['p'] : (isset($_POST['p']) ? $_POST['p'] : '');

// clean path
$p = clean_path($p);

/*************************** ACTIONS ***************************/

// Delete file / folder
if (isset($_GET['del'])) {
	$del = $_GET['del'];
	$del = clean_path($del);
	$del = str_replace('/', '', $del);
	if ($del != '' && $del != '..' && $del != '.') {
		$path = ABSPATH;
		if ($p != '') $path .= DS . $p;
		$is_dir = (is_dir($path . DS . $del)) ? true : false;
		if (rdelete($path . DS . $del)) {
			$msg = $is_dir ? __('Folder <b>%s</b> deleted') : __('File <b>%s</b> deleted');
			set_message(sprintf($msg, $del));
		}
		else {
			$msg = $is_dir ? __('Folder <b>%s</b> not deleted') : __('File <b>%s</b> not deleted');
			set_message(sprintf($msg, $del), 'error');
		}
	}
	else {
		set_message(__('Wrong file or folder name'), 'error');
	}
	redirect(BASE_URL . '?p=' . urlencode($p));
}

// Create folder
if (isset($_GET['new'])) {
	$new = $_GET['new'];
	$new = clean_path($new);
	$new = str_replace('/', '', $new);
	if ($new != '' && $new != '..' && $new != '.') {
		$path = ABSPATH;
		if ($p != '') $path .= DS . $p;
		if (mkdir_safe($path . DS . $new, false) === true) {
			set_message(sprintf(__('Folder <b>%s</b> created'), $new));
		}
		elseif (mkdir_safe($path . DS . $new, false) === $path . DS . $new) {
			set_message(sprintf(__('Folder <b>%s</b> already exists'), $new), 'alert');
		}
		else {
			set_message(sprintf(__('Folder <b>%s</b> not created'), $new), 'error');
		}
	}
	else {
		set_message(__('Wrong folder name'), 'error');
	}
	redirect(BASE_URL . '?p=' . urlencode($p));
}

// Copy folder / file
if (isset($_GET['copy']) && isset($_GET['finish'])) {
	// from
	$copy = $_GET['copy'];
	$copy = clean_path($copy);
	// empty path
	if ($copy == '') {
		set_message(__('Source path not defined'), 'error');
		redirect(BASE_URL . '?p=' . urlencode($p));
	}
	// abs path from
	$from = ABSPATH . DS . $copy;
	// abs path to
	$dest = ABSPATH;
	if ($p != '') $dest .= DS . $p;
	$dest .= DS . basename($from);
	// move?
	$move = (isset($_GET['move'])) ? true : false;
	// copy/move
	if ($from != $dest) {
		$msg_from = trim($p . DS . basename($from), DS);
		if ($move) {
			$rename = rename_safe($from, $dest);
			if ($rename) {
				set_message(sprintf(__('Moved from <b>%s</b> to <b>%s</b>'), $copy, $msg_from));
			}
			elseif ($rename === null) {
				set_message(__('File or folder with this path already exists'), 'alert');
			}
			else {
				set_message(sprintf(__('Error while moving from <b>%s</b> to <b>%s</b>'), $copy, $msg_from), 'error');
			}
		}
		else {
			if (rcopy($from, $dest)) {
				set_message(sprintf(__('Copyied from <b>%s</b> to <b>%s</b>'), $copy, $msg_from));
			}
			else {
				set_message(sprintf(__('Error while copying from <b>%s</b> to <b>%s</b>'), $copy, $msg_from), 'error');
			}
		}
	}
	else {
		set_message(__('Paths must be not equal'), 'alert');
	}
	redirect(BASE_URL . '?p=' . urlencode($p));
}

// Mass copy files/ folders
if (isset($_POST['file']) && isset($_POST['copy_to']) && isset($_POST['finish'])) {
	// from
	$path = ABSPATH;
	if ($p != '') $path .= DS . $p;
	// to
	$copy_to_path = ABSPATH;
	$copy_to = clean_path($_POST['copy_to']);
	if ($copy_to != '') $copy_to_path .= DS . $copy_to;
	if ($path == $copy_to_path) {
		set_message(__('Paths must be not equal'), 'alert');
		redirect(BASE_URL . '?p=' . urlencode($p));
	}
	if (!is_dir($copy_to_path)) {
		if (!mkdir_safe($copy_to_path, true)) {
			set_message(__('Unable to create destination folder'), 'error');
			redirect(BASE_URL . '?p=' . urlencode($p));
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
				$from = $path . DS . $f;
				// abs path to
				$dest = $copy_to_path . DS . $f;
				// do
				if ($move) {
					$rename = rename_safe($from, $dest);
					if ($rename === false) {
						$errors++;
					}
				}
				else {
					if (!rcopy($from, $dest)) {
						$errors++;
					}
				}
			}
		}
		if ($errors == 0) {
			$msg = $move ? __('Selected files and folders moved') : __('Selected files and folders copied');
			set_message($msg);
		}
		else {
			$msg = $move ? __('Error while moving items') : __('Error while copying items');
			set_message($msg, 'error');
		}
	}
	else {
		set_message(__('Nothing selected'), 'alert');
	}
	redirect(BASE_URL . '?p=' . urlencode($p));
}

// Rename
if (isset($_GET['ren']) && isset($_GET['to'])) {
	// old name
	$old = $_GET['ren'];
	$old = clean_path($old);
	$old = str_replace('/', '', $old);
	// new name
	$new = $_GET['to'];
	$new = clean_path($new);
	$new = str_replace('/', '', $new);
	// path
	$path = ABSPATH;
	if ($p != '') $path .= DS . $p;
	// rename
	if ($old != '' && $new != '') {
		if (rename_safe($path . DS . $old, $path . DS . $new)) {
			set_message(sprintf(__('Renamed from <b>%s</b> to <b>%s</b>'), $old, $new));
		}
		else {
			set_message(sprintf(__('Error while renaming from <b>%s</b> to <b>%s</b>'), $old, $new), 'error');
		}
	}
	else {
		set_message(__('Names not set'), 'error');
	}
	redirect(BASE_URL . '?p=' . urlencode($p));
}

// Download
if (isset($_GET['dl'])) {
	$dl = $_GET['dl'];
	$dl = clean_path($dl);
	$dl = str_replace('/', '', $dl);
	$path = ABSPATH;
	if ($p != '') $path .= DS . $p;
	if ($dl != '' && is_file($path . DS . $dl)) {
		header('Content-Description: File Transfer');
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename="' . basename($path . DS . $dl) . '"');
		header('Content-Transfer-Encoding: binary');
		header('Connection: Keep-Alive');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
		header('Content-Length: ' . filesize($path . DS . $dl));
		readfile($path . DS . $dl);
		exit;
	}
	else {
		set_message(__('File not found'), 'error');
		redirect(BASE_URL . '?p=' . urlencode($p));
	}
}

// Upload
if (isset($_POST['upl'])) {
	$path = ABSPATH;
	if ($p != '') $path .= DS . $p;

	$errors = 0;
	$uploads = 0;
	$total = count($_FILES['upload']['name']);

	for ($i = 0; $i < $total; $i++) {
		$tmp_name = $_FILES['upload']['tmp_name'][$i];
		if (empty($_FILES['upload']['error'][$i]) && !empty($tmp_name) && $tmp_name != 'none') {
			if (move_uploaded_file($tmp_name, $path . DS . $_FILES['upload']['name'][$i])) {
				$uploads++;
			}
			else {
				$errors++;
			}
		}
	}

	if ($errors == 0 && $uploads > 0) {
		set_message(sprintf(__('All files uploaded to <b>%s</b>'), $path));
	}
	elseif ($errors == 0 && $uploads == 0) {
		set_message(__('Nothing uploaded'), 'alert');
	}
	else {
		set_message(sprintf(__('Error while uploading files. Uploaded files: %s'), $uploads), 'error');
	}

	redirect(BASE_URL . '?p=' . urlencode($p));
}

// Mass deleting
if (isset($_POST['group']) && isset($_POST['delete'])) {
	$path = ABSPATH;
	if ($p != '') $path .= DS . $p;

	$errors = 0;
	$files = $_POST['file'];
	if (is_array($files) && count($files)) {
		foreach ($files as $f) {
			if ($f != '') {
				$new_path = $path . DS . $f;
				if (!rdelete($new_path)) {
					$errors++;
				}
			}
		}
		if ($errors == 0) {
			set_message(__('Selected files and folder deleted'));
		}
		else {
			set_message(__('Error while deleting items'), 'error');
		}
	}
	else {
		set_message(__('Nothing selected'), 'alert');
	}

	redirect(BASE_URL . '?p=' . urlencode($p));
}

// Pack files
if (isset($_POST['group']) && isset($_POST['zip'])) {
	$path = ABSPATH;
	if ($p != '') $path .= DS . $p;

	if (!class_exists('ZipArchive')) {
		set_message(__('Operations with archives are not available'), 'error');
		redirect(BASE_URL . '?p=' . urlencode($p));
	}

	$files = $_POST['file'];
	if (!empty($files)) {
		chdir($path);

		$zipname = 'archive_' . date('ymd_His') . '.zip';

		$zipper = new Zipper;
		$res = $zipper->create($zipname, $files);

		if ($res) {
			set_message(sprintf(__('Archive <b>%s</b> created'), $zipname));
		}
		else {
			set_message(__('Archive not created'), 'error');
		}
	}
	else {
		set_message(__('Nothing selected'), 'alert');
	}

	redirect(BASE_URL . '?p=' . urlencode($p));
}

// Unpack
if (isset($_GET['unzip'])) {
	$unzip = $_GET['unzip'];
	$unzip = clean_path($unzip);
	$unzip = str_replace('/', '', $unzip);

	$path = ABSPATH;
	if ($p != '') $path .= DS . $p;

	if (!class_exists('ZipArchive')) {
		set_message(__('Operations with archives are not available'), 'error');
		redirect(BASE_URL . '?p=' . urlencode($p));
	}

	if ($unzip != '' && is_file($path . DS . $unzip)) {

		$zip_path = $path . DS . $unzip;

		//to folder
		$tofolder = '';
		if (isset($_GET['tofolder'])) {
			$tofolder = pathinfo($zip_path, PATHINFO_FILENAME);
			if (mkdir_safe($path . DS . $tofolder, true)) {
				$path .= DS . $tofolder;
			}
		}

		$zipper = new Zipper;
		$res = $zipper->unzip($zip_path, $path);

		if ($res) {
			set_message(__('Archive unpacked'));
		}
		else {
			set_message(__('Archive not unpacked'), 'error');
		}

	}
	else {
		set_message(__('File not found'), 'error');
	}
	redirect(BASE_URL . '?p=' . urlencode($p));
}

// Change Perms
if (isset($_POST['chmod'])) {
	$path = ABSPATH;
	if ($p != '') $path .= DS . $p;

	$file = $_POST['chmod'];
	$file = clean_path($file);
	$file = str_replace('/', '', $file);
	if ($file == '' || (!is_file($path . DS . $file) && !is_dir($path . DS . $file))) {
		set_message(__('File not found'), 'error');
		redirect(BASE_URL . '?p=' . urlencode($p));
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

	if (@chmod($path . DS . $file, $mode)) {
		set_message(__('Permissions changed'));
	}
	else {
		set_message(__('Permissions not changed'), 'error');
	}

	redirect(BASE_URL . '?p=' . urlencode($p));
}

/*************************** /ACTIONS ***************************/

// get current path
$path = ABSPATH;
if ($p != '') $path .= DS . $p;

// check path
if (!is_dir($path)) {
	redirect(BASE_URL . '?p=');
}

// get parent folder
$parent = get_parent_path($p);

$objects = scandir($path);
$folders = array();
$files = array();
if (is_array($objects)) {
	foreach ($objects as $file) {
		$new_path = $path . DS . $file;
		if (is_file($new_path)) {
			$files[] = $file;
		}
		elseif (is_dir($new_path) && $file != '.' && $file != '..') {
			$folders[] = $file;
		}
	}
}

if (!empty($files)) natcasesort($files);
if (!empty($folders)) natcasesort($folders);

### upload form
if (isset($_GET['upload'])) {
	show_header(); // HEADER
	show_navigation_path($p); // current path
	?>
	<div class="path">
		<p><b><?php _e('Uploading files') ?></b></p>

		<p><?php _e('Destination folder:') ?> <?php echo ABSPATH  . '/' .  $p ?></p>

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

### copy form POST
if (isset($_POST['copy'])) {
	$copy_files = $_POST['file'];
	if (!is_array($copy_files) || empty($copy_files)) {
		set_message(__('Nothing selected'), 'alert');
		redirect(BASE_URL . '?p=' . urlencode($p));
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

			<p><?php _e('Files:') ?> <b><?php echo implode('</b>, <b>', $copy_files) ?></b> </p>

			<p><?php _e('Source folder:') ?> <?php echo ABSPATH ?>/<?php echo $p ?><br>
				<label for="inp_copy_to"><?php _e('Destination folder:') ?></label>
				<?php echo ABSPATH ?>/<input type="text" name="copy_to" id="inp_copy_to" value="<?php echo encode_html($p) ?>">
			</p>

			<p><label><input type="checkbox" name="move" value="1"> <?php _e('Move') ?></label></p>
			<p>
				<button type="submit" class="btn"><i class="icon-apply"></i> <?php _e('Copy') ?></button> &nbsp;
				<b><a href="?p=<?php echo urlencode($p) ?>"><i class="icon-cancel"></i> <?php _e('Cancel') ?></a></b>
			</p>
		</form>

		<!--<p><i><?php /*_e('Select folder:')*/ ?></i></p>
		<ul class="folders"></ul>-->

	</div>
	<?php
	show_footer();
	exit;
}

### copy form
if (isset($_GET['copy']) && !isset($_GET['finish'])) {
	$copy = $_GET['copy'];
	$copy = clean_path($copy);
	if ($copy == '' || !file_exists(ABSPATH . DS . $copy)) {
		set_message(__('File not found'), 'error');
		redirect(BASE_URL . '?p=' . urlencode($p));
	}

	show_header(); // HEADER
	show_navigation_path($p); // current path
	?>
	<div class="path">
		<p><b><?php _e('Copying') ?></b></p>

		<p><?php _e('Source path:') ?> <?php echo ABSPATH ?>/<?php echo $copy ?><br>
			<?php _e('Destination folder:') ?> <?php echo ABSPATH ?>/<?php echo $p ?>
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
				<li><a href="?p=<?php echo urlencode(trim($p . DS . $f, DS)) ?>&amp;copy=<?php echo urlencode($copy) ?>"><i class="icon-folder"></i> <?php echo $f ?></a></li>
			<?php
			}
			?>
		</ul>
	</div>
	<?php
	show_footer();
	exit;
}

### zip info
if (isset($_GET['zip'])) {
	$file = $_GET['zip'];
	$file = clean_path($file);
	$file = str_replace('/', '', $file);
	if ($file == '' || !is_file($path . DS . $file)) {
		set_message(__('File not found'), 'error');
		redirect(BASE_URL . '?p=' . urlencode($p));
	}

	show_header(); // HEADER
	show_navigation_path($p); // current path

	$file_url = 'http://' . getenv('HTTP_HOST') . '/' . $p . '/' . $file;
	$file_path = $path . DS . $file;
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
				<b><a href="?p=<?php echo urlencode($p) ?>&amp;unzip=<?php echo urlencode($file) ?>"><i class="icon-apply"></i> <?php _e('Unpack') ?></a></b> &nbsp;
				<b><a href="?p=<?php echo urlencode($p) ?>&amp;unzip=<?php echo urlencode($file) ?>&amp;tofolder=1" title="<?php _e('Unpack to') ?> <?php echo encode_html($zip_name) ?>"><i class="icon-apply"></i>
						<?php _e('Unpack to folder') ?></a></b> &nbsp;
				<b><a href="?p=<?php echo urlencode($p) ?>"><i class="icon-goback"></i> <?php _e('Back') ?></a></b>
			</p>

			<code class="maxheight">
			<?php
			foreach ($filenames as $fn) {
				if ($fn['folder']) {
					echo '<b>' . $fn['name'] . '</b><br>';
				}
				else {
					echo $fn['name'] . ' (' . get_filesize($fn['filesize']) . ')<br>';
				}
			}
			?>
			</code>
		<?php
		}
		else {
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

### image info
if (isset($_GET['showimg'])) {
	$file = $_GET['showimg'];
	$file = clean_path($file);
	$file = str_replace('/', '', $file);
	if ($file == '' || !is_file($path . DS . $file)) {
		set_message(__('File not found'), 'error');
		redirect(BASE_URL . '?p=' . urlencode($p));
	}

	show_header(); // HEADER
	show_navigation_path($p); // current path

	$file_url = 'http://' . getenv('HTTP_HOST') . '/' . $p . '/' . $file;
	$file_path = $path . DS . $file;

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

### txt info
if (isset($_GET['showtxt'])) {
	$file = $_GET['showtxt'];
	$file = clean_path($file);
	$file = str_replace('/', '', $file);
	if ($file == '' || !is_file($path . DS . $file)) {
		set_message(__('File not found'), 'error');
		redirect(BASE_URL . '?p=' . urlencode($p));
	}

	show_header(); // HEADER
	show_navigation_path($p); // current path

	$file_url = 'http://' . getenv('HTTP_HOST') . '/' . $p . '/' . $file;
	$file_path = $path . DS . $file;

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
	}
	else {
		$content = '<pre>' . encode_html($content) . '</pre>';
	}

	?>
	<div class="path">
		<p><b><?php _e('File') ?> <?php echo $file ?></b></p>

		<p>
			<?php _e('Full path:') ?> <?php echo $file_path ?><br>
			<?php _e('File size:') ?> <?php echo get_filesize(filesize($file_path)) ?><br>
			<?php _e('MIME-type:') ?> <?php echo get_mime_type($file_path) ?><br>
			<?php _e('Charset:') ?> <?php echo ($is_utf8) ? 'utf-8' : 'windows-1251' ?>
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

### chmod
if (isset($_GET['chmod'])) {
	$file = $_GET['chmod'];
	$file = clean_path($file);
	$file = str_replace('/', '', $file);
	if ($file == '' || (!is_file($path . DS . $file) && !is_dir($path . DS . $file))) {
		set_message(__('File not found'), 'error');
		redirect(BASE_URL . '?p=' . urlencode($p));
	}

	show_header(); // HEADER
	show_navigation_path($p); // current path

	$file_url = 'http://' . getenv('HTTP_HOST') . '/' . $p . '/' . $file;
	$file_path = $path . DS . $file;

	$mode = fileperms($path . DS . $file);

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
<table><tr><th style="width:3%"><label><input type="checkbox" title="<?php _e('Invert selection') ?>" onclick="checkbox_toggle()"></label></th><th style="width:58%"><?php _e('Name') ?></th><th style="width:10%"><?php _e('Size') ?></th><th style="width:12%"><?php _e('Modified') ?></th><th style="width:6%"><?php _e('Perms') ?></th><th style="width:11%"></th></tr>
<?php

			// link to parent folder
			if ($parent !== false) {
				?>
<tr><td></td><td colspan="5"><a href="?p=<?php echo urlencode($parent) ?>"><i class="icon-arrow_up"></i> ..</a></td></tr>
<?php
			}

			foreach ($folders as $f) {
				$modif = date("d.m.y H:i", filemtime($path . DS . $f));
				$perms = substr(decoct(fileperms($path . DS . $f)), -4);
				?>
<tr><td><label><input type="checkbox" name="file[]" value="<?php echo encode_html($f) ?>"></label></td>
<td><a href="?p=<?php echo urlencode(trim($p . DS . $f, DS)) ?>"><i class="icon-folder"></i> <?php echo $f ?></a></td>
<td><?php _e('Folder') ?></td><td><?php echo $modif ?></td>
<td><a title="<?php _e('Change Permissions') ?>" href="?p=<?php echo urlencode($p) ?>&amp;chmod=<?php echo urlencode($f) ?>"><?php echo $perms ?></a></td>
<td>
<a title="<?php _e('Delete') ?>" href="?p=<?php echo urlencode($p) ?>&amp;del=<?php echo urlencode($f) ?>" onclick="return confirm('<?php _e('Delete folder?') ?>');"><i class="icon-cross"></i></a>
<a title="<?php _e('Rename') ?>" href="#" onclick="rename('<?php echo encode_html($p) ?>', '<?php echo encode_html($f) ?>');return false;"><i class="icon-rename"></i></a>
<a title="<?php _e('Copy to...') ?>" href="?p=&amp;copy=<?php echo urlencode(trim($p . DS . $f, DS)) ?>"><i class="icon-copy"></i></a>
</td></tr>
<?php
				flush();
			}

			foreach ($files as $f) {
				$img = get_file_icon($path . DS . $f);
				$modif = date("d.m.y H:i", filemtime($path . DS . $f));
				$filesize_raw = filesize($path . DS . $f);
				$filesize = get_filesize($filesize_raw);
				$filelink = get_file_link($p, $f);
				$all_files_size += $filesize_raw;
				$perms = substr(decoct(fileperms($path . DS . $f)), -4);
				?>
<tr><td><label><input type="checkbox" name="file[]" value="<?php echo encode_html($f) ?>"></label></td><td>
<?php if (!empty($filelink)) echo '<a href="' . $filelink . '" title="' . __('File info') . '">' ?><i class="<?php echo $img ?>"></i> <?php echo $f ?><?php if (!empty($filelink)) echo '</a>' ?></td>
<td><span title="<?php printf(__('%s byte'), $filesize_raw) ?>"><?php echo $filesize ?></span></td>
<td><?php echo $modif ?></td>
<td><a title="<?php _e('Change Permissions') ?>" href="?p=<?php echo urlencode($p) ?>&amp;chmod=<?php echo urlencode($f) ?>"><?php echo $perms ?></a></td>
<td>
<a title="<?php _e('Delete') ?>" href="?p=<?php echo urlencode($p) ?>&amp;del=<?php echo urlencode($f) ?>" onclick="return confirm('<?php _e('Delete file?') ?>');"><i class="icon-cross"></i></a>
<a title="<?php _e('Rename') ?>" href="#" onclick="rename('<?php echo encode_html($p) ?>', '<?php echo encode_html($f) ?>');return false;"><i class="icon-rename"></i></a>
<a title="<?php _e('Copy to...') ?>" href="?p=<?php echo urlencode($p) ?>&amp;copy=<?php echo urlencode(trim($p . DS . $f, DS)) ?>"><i class="icon-copy"></i></a>
<a title="<?php _e('Download') ?>" href="?p=<?php echo urlencode($p) ?>&amp;dl=<?php echo urlencode($f) ?>"><i class="icon-download"></i></a>
</td></tr>
<?php
				flush();
			}

			if (empty($folders) && empty($files)) {
				?>
<tr><td></td><td colspan="5"><em><?php _e('Folder is empty') ?></em></td></tr>
<?php
			}
			else {
				?>
<tr><td class="gray"></td><td class="gray" colspan="5">
<?php _e('Full size:') ?> <span title="<?php printf(__('%s byte'), $all_files_size) ?>"><?php echo get_filesize($all_files_size) ?></span>,
<?php _e('files:') ?> <?php echo $num_files ?>,
<?php _e('folders:') ?> <?php echo $num_folders ?>
</td></tr>
<?php
			}
			?>
</table>
<p class="path"><a href="#" onclick="select_all();return false;"><i class="icon-checkbox"></i> <?php _e('Select all') ?></a> &nbsp;
<a href="#" onclick="unselect_all();return false;"><i class="icon-checkbox_uncheck"></i> <?php _e('Unselect all') ?></a> &nbsp;
<a href="#" onclick="invert_all();return false;"><i class="icon-checkbox_invert"></i> <?php _e('Invert selection') ?></a></p>
<p><input type="submit" name="delete" value="<?php _e('Delete') ?>" onclick="return confirm('<?php _e('Delete selected files and folders?') ?>')">
<input type="submit" name="zip" value="<?php _e('Pack') ?>">
<input type="submit" name="copy" value="<?php _e('Copy') ?>"></p>
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
					if (!rdelete($path . DS . $file)) {
						$ok = false;
					}
				}
			}
		}
		return ($ok) ? rmdir($path) : false;
	}
	elseif (is_file($path)) {
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
					if (!rchmod($path . DS . $file, $filemode, $dirmode)) return false;
				}
			}
		}
		return true;
	}
	elseif (is_link($path)) {
		return true;
	}
	elseif (is_file($path)) {
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
					if (!rcopy($path . DS . $file, $dest . DS . $file)) {
						$ok = false;
					}
				}
			}
		}
		return $ok;
	}
	elseif (is_file($path)) {
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
	}
	elseif (function_exists('mime_content_type')) {
		return mime_content_type($file_path);
	}
	elseif (!stristr(ini_get('disable_functions'), 'shell_exec')) {
		$file = escapeshellarg($file_path);
		$mime = shell_exec('file -bi ' . $file);
		return $mime;
	}
	else {
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
	return str_replace('\\', DS, $path);
}

function get_parent_path($path)
{
	$path = clean_path($path);
	if ($path != '') {
		$array = explode(DS, $path);
		if (count($array) > 1) {
			$array = array_slice($array, 0, -1);
			return implode(DS, $array);
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
					'name'            => $zip_name,
					'filesize'        => zip_entry_filesize($zip_entry),
					'compressed_size' => zip_entry_compressedsize($zip_entry),
					'folder'          => $zip_folder
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
				if (is_file($path . DS . $file)) {
					$files[] = $path . DS . $file;
				}
				elseif (is_dir($path . DS . $file) && $file != '.' && $file != '..') {
					$files = array_merge($files, get_files_recursive($path . DS . $file));
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

	$path = ABSPATH;
	if ($p != '') $path .= DS . $p;

	if (!empty($f)) {
		$path .= DS . $f;
		// get extension
		$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

		switch ($ext) {
			case 'zip':
				$link = '?p=' . urlencode($p) . '&amp;zip=' . urlencode($f); break;
			case 'ico': case 'gif': case 'jpg': case 'jpeg': case 'jpc': case 'jp2': case 'jpx':
			case 'xbm': case 'wbmp': case 'png': case 'bmp': case 'tif': case 'tiff': case 'psd':
				$link = '?p=' . urlencode($p) . '&amp;showimg=' . urlencode($f); break;
			case 'txt': case 'css': case 'ini': case 'conf': case 'log': case 'htaccess':
			case 'passwd': case 'ftpquota': case 'sql': case 'js': case 'json': case 'sh':
			case 'config': case 'php': case 'php4': case 'php5': case 'phps': case 'phtml':
			case 'htm': case 'html': case 'shtml': case 'xhtml': case 'xml': case 'xsl':
			case 'm3u': case 'm3u8': case 'pls': case 'cue': case 'eml': case 'msg':
			case 'csv': case 'bat': case 'twig': case 'tpl':
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
		case 'config': case 'twig': case 'tpl':
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
			$img = 'icon-file_csv';  break;
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

/*
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
		}
		else {
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
		}
		elseif (is_dir($filename)) {
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
					if (is_dir($path . DS . $file)) {
						if (!$this->addDir($path . DS . $file)) {
							return false;
						}
					}
					elseif (is_file($path . DS . $file)) {
						if (!$this->zip->addFile($path . DS . $file)) {
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
<a title="<?php _e('Upload files') ?>" href="?p=<?php echo urlencode($p) ?>&amp;upload"><i class="icon-upload"></i></a>
<a title="<?php _e('New folder') ?>" href="#" onclick="newfolder('<?php echo encode_html($p) ?>');return false;"><i class="icon-folder_add"></i></a>
<?php if ($use_auth && !empty($auth_users)): ?><a title="<?php _e('Logout') ?>" href="?logout=1"><i class="icon-logout"></i></a><?php endif; ?>
</div>
		<?php
		$path = clean_path($path);
		$root_url = "<a href='?p='><i class='icon-home' title='" . ABSPATH . "'></i></a>";
		$sep = '<i class="icon-separator"></i>';
		if ($path != '') {
			$exploded = explode('/', $path);
			$count = count($exploded);
			$array = array();
			$parent = '';
			for ($i = 0; $i < $count; $i++) {
				$parent = trim($parent . DS . $exploded[$i], DS);
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
[class*="icon-"]{display:inline-block;width:16px;height:16px;background:url("<?php echo BASE_URL ?>?img=sprites") no-repeat 0 0;vertical-align:bottom}
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
.icon-logout{background-position:-304px 0}
.compact-table{border:0;width:auto}.compact-table td,.compact-table th{width:100px;border:0;text-align:center}.compact-table tr:hover td{background-color:#fff}
</style>
<link rel="icon" href="<?php echo BASE_URL ?>?img=favicon" type="image/png">
<link rel="shortcut icon" href="<?php echo BASE_URL ?>?img=favicon" type="image/png">
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
		}
		else {
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
		'sprites' => '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',
	);
}

function get_strings($lang)
{
	$strings['ru'] = array(
		'Folder <b>%s</b> deleted'                         => 'Папка <b>%s</b> удалена',
		'Folder <b>%s</b> not deleted'                     => 'Папка <b>%s</b> не удалена',
		'File <b>%s</b> deleted'                           => 'Файл <b>%s</b> удален',
		'File <b>%s</b> not deleted'                       => 'Файл <b>%s</b> не удален',
		'Wrong file or folder name'                        => 'Имя папки или файла задано не верно',
		'Folder <b>%s</b> created'                         => 'Папка <b>%s</b> создана',
		'Folder <b>%s</b> already exists'                  => 'Папка <b>%s</b> уже существует',
		'Folder <b>%s</b> not created'                     => 'Папка <b>%s</b> не создана',
		'Wrong folder name'                                => 'Имя папки задано не верно',
		'Source path not defined'                          => 'Не задан исходный путь',
		'Moved from <b>%s</b> to <b>%s</b>'                => 'Перемещено из <b>%s</b> в <b>%s</b>',
		'File or folder with this path already exists'     => 'Файл или папка уже есть по указанному пути',
		'Error while moving from <b>%s</b> to <b>%s</b>'   => 'Произошла ошибка при перемещении из <b>%s</b> в <b>%s</b>',
		'Copyied from <b>%s</b> to <b>%s</b>'              => 'Скопировано из <b>%s</b> в <b>%s</b>',
		'Error while copying from <b>%s</b> to <b>%s</b>'  => 'Произошла ошибка при копировании из <b>%s</b> в <b>%s</b>',
		'Paths must be not equal'                          => 'Пути не должны совпадать',
		'Unable to create destination folder'              => 'Невозможно создать папку назначения',
		'Selected files and folders moved'                 => 'Все отмеченные файлы и папки перемещены',
		'Selected files and folders copied'                => 'Все отмеченные файлы и папки сопированы',
		'Error while moving items'                         => 'При перемещении возникли ошибки',
		'Error while copying items'                        => 'При копировании возникли ошибки',
		'Nothing selected'                                 => 'Ничего не выбрано',
		'Renamed from <b>%s</b> to <b>%s</b>'              => 'Переименовано из <b>%s</b> в <b>%s</b>',
		'Error while renaming from <b>%s</b> to <b>%s</b>' => 'Произошла ошибка при переименовании из <b>%s</b> в <b>%s</b>',
		'Names not set'                                    => 'Не заданы имена',
		'File not found'                                   => 'Файл не найден',
		'All files uploaded to <b>%s</b>'                  => 'Все файлы загружены в папку <b>%s</b>',
		'Nothing uploaded'                                 => 'Ничего не загружено',
		'Error while uploading files. Uploaded files: %s'  => 'При загрузке файлов возникли ошибки. Загружено файлов: %s',
		'Selected files and folder deleted'                => 'Все отмеченные файлы и папки удалены',
		'Error while deleting items'                       => 'При удалении возникли ошибки',
		'Archive <b>%s</b> created'                        => 'Архив <b>%s</b> успешно создан',
		'Archive not created'                              => 'Ошибка. Архив не создан',
		'Archive unpacked'                                 => 'Архив распакован',
		'Archive not unpacked'                             => 'Архив не распакован',
		'Uploading files'                                  => 'Загрузка файлов',
		'Destination folder:'                              => 'Папка назначения:',
		'Upload'                                           => 'Загрузить',
		'Cancel'                                           => 'Отмена',
		'Copying'                                          => 'Копирование',
		'Files:'                                           => 'Файлы:',
		'Source folder:'                                   => 'Исходная папка:',
		'Move'                                             => 'Переместить',
		'Select folder:'                                   => 'Выбрать папку:',
		'Source path:'                                     => 'Исходный путь:',
		'Archive'                                          => 'Архив',
		'Full path:'                                       => 'Полный путь:',
		'File size:'                                       => 'Размер файла:',
		'Files in archive:'                                => 'Файлов в архиве:',
		'Total size:'                                      => 'Общий размер:',
		'Size in archive:'                                 => 'Размер в архиве:',
		'Compression:'                                     => 'Степень сжатия:',
		'Open'                                             => 'Открыть',
		'Unpack'                                           => 'Распаковать',
		'Unpack to'                                        => 'Распаковать в',
		'Unpack to folder'                                 => 'Распаковать в папку',
		'Back'                                             => 'Назад',
		'Error while fetching archive info'                => 'Ошибка получения информации об архиве',
		'Image'                                            => 'Изображение',
		'MIME-type:'                                       => 'MIME-тип:',
		'Image sizes:'                                     => 'Размеры изображения:',
		'File'                                             => 'Файл',
		'Charset:'                                         => 'Кодировка:',
		'Name'                                             => 'Имя',
		'Size'                                             => 'Размер',
		'Modified'                                         => 'Изменен',
		'Folder'                                           => 'Папка',
		'Delete'                                           => 'Удалить',
		'Delete folder?'                                   => 'Удалить папку?',
		'Delete file?'                                     => 'Удалить файл?',
		'Rename'                                           => 'Переименовать',
		'Copy to...'                                       => 'Копировать в...',
		'File info'                                        => 'Информация о файле',
		'%s byte'                                          => '%s байт',
		'%s KB'                                            => '%s КБ',
		'%s MB'                                            => '%s МБ',
		'%s GB'                                            => '%s ГБ',
		'Download'                                         => 'Скачать',
		'Folder is empty'                                  => 'Папка пуста',
		'Select all'                                       => 'Выделить все',
		'Unselect all'                                     => 'Снять выделение',
		'Invert selection'                                 => 'Инвертировать выделение',
		'Delete selected files and folders?'               => 'Удалить выбранные файлы и папки?',
		'Pack'                                             => 'Упаковать',
		'Copy'                                             => 'Копировать',
		'Upload files'                                     => 'Загрузить файлы',
		'New folder'                                       => 'Новая папка',
		'New folder name'                                  => 'Имя новой папки',
		'New name'                                         => 'Новое имя',
		'Operations with archives are not available'       => 'Операции с архивами недоступны',
		'Full size:'                                       => 'Общий размер:',
		'files:'                                           => 'файлов:',
		'folders:'                                         => 'папок:',
		'Perms'                                            => 'Права',
		'Username'                                         => 'Имя пользователя',
		'Password'                                         => 'Пароль',
		'Login'                                            => 'Войти',
		'Logout'                                           => 'Выход',
		'Wrong password'                                   => 'Неверный пароль',
		'You are logged in'                                => 'Вы успешно вошли',
		'Change Permissions'                               => 'Изменение прав доступа',
		'Permissions:'                                     => 'Права доступа:',
		'Change'                                           => 'Изменить',
		'Owner'                                            => 'Владелец',
		'Group'                                            => 'Группа',
		'Other'                                            => 'Прочие',
		'Read'                                             => 'Чтение',
		'Write'                                            => 'Запись',
		'Execute'                                          => 'Выполнение',
		'Permissions changed'                              => 'Права изменены',
		'Permissions not changed'                          => 'Права не изменены',
	);
	$strings['fr'] = array(
		'Folder <b>%s</b> deleted'                         => 'Dossier <b>%s</b> supprimé',
		'Folder <b>%s</b> not deleted'                     => 'Dossier <b>%s</b> non supprimé',
		'File <b>%s</b> deleted'                           => 'Fichier <b>%s</b> supprimé',
		'File <b>%s</b> not deleted'                       => 'Fichier <b>%s</b> non supprimé',
		'Wrong file or folder name'                        => 'Nom de fichier ou dossier incorrect',
		'Folder <b>%s</b> created'                         => 'Dossier <b>%s</b> créé',
		'Folder <b>%s</b> already exists'                  => 'Dossier <b>%s</b> déjà existant',
		'Folder <b>%s</b> not created'                     => 'Dossier <b>%s</b> non créé',
		'Wrong folder name'                                => 'Nom de dossier inccorect',
		'Source path not defined'                          => 'Chemin source non défini',
		'Moved from <b>%s</b> to <b>%s</b>'                => 'Déplacé de <b>%s</b> à <b>%s</b>',
		'File or folder with this path already exists'     => 'Fichier ou dossier avec ce chemin déjà existant',
		'Error while moving from <b>%s</b> to <b>%s</b>'   => 'Erreur lors du déplacement de <b>%s</b> à <b>%s</b>',
		'Copyied from <b>%s</b> to <b>%s</b>'              => 'Copié de <b>%s</b> à <b>%s</b>',
		'Error while copying from <b>%s</b> to <b>%s</b>'  => 'Erreur lors de la copie de <b>%s</b> à <b>%s</b>',
		'Paths must be not equal'                          => 'Les chemins doivent être différents',
		'Unable to create destination folder'              => 'Impossible de créer le dossier de destination',
		'Selected files and folders moved'                 => 'Fichiers et dossiers sélectionnés déplacés',
		'Selected files and folders copied'                => 'Fichiers et dossiers sélectionnés copiés',
		'Error while moving items'                         => 'Erreur lors du déplacement des éléments',
		'Error while copying items'                        => 'Erreur lors de la copie des éléments',
		'Nothing selected'                                 => 'Sélection vide',
		'Renamed from <b>%s</b> to <b>%s</b>'              => 'Renommé de <b>%s</b> à <b>%s</b>',
		'Error while renaming from <b>%s</b> to <b>%s</b>' => 'Erreur lors du renommage de <b>%s</b> en <b>%s</b>',
		'Names not set'                                    => 'Noms indéfinis',
		'File not found'                                   => 'Fichier non trouvé',
		'All files uploaded to <b>%s</b>'                  => 'Tous les fichiers ont été envoyé dans <b>%s</b>',
		'Nothing uploaded'                                 => 'Rien a été envoyé',
		'Error while uploading files. Uploaded files: %s'  => 'Erreur lors de l\'envoi des fichiers. Fichiers envoyés : %s',
		'Selected files and folder deleted'                => 'Fichiers et dossier sélectionnés supprimés',
		'Error while deleting items'                       => 'Erreur lors de la suppression des éléments',
		'Archive <b>%s</b> created'                        => 'Archive <b>%s</b> créée',
		'Archive not created'                              => 'Archive non créée',
		'Archive unpacked'                                 => 'Archive décompressée',
		'Archive not unpacked'                             => 'Archive non décompressée',
		'Uploading files'                                  => 'Envoie des fichiers',
		'Destination folder:'                              => 'Dossier de destination :',
		'Upload'                                           => 'Envoi',
		'Cancel'                                           => 'Annuler',
		'Copying'                                          => 'Copie en cours',
		'Files:'                                           => 'Fichiers :',
		'Source folder:'                                   => 'Dossier source :',
		'Move'                                             => 'Déplacer',
		'Select folder:'                                   => 'Dossier sélectionné :',
		'Source path:'                                     => 'Chemin source :',
		'Archive'                                          => 'Archive',
		'Full path:'                                       => 'Chemin complet :',
		'File size:'                                       => 'Taille du fichier :',
		'Files in archive:'                                => 'Fichiers dans l\'archive :',
		'Total size:'                                      => 'Taille totale :',
		'Size in archive:'                                 => 'Taille dans l\'archive :',
		'Compression:'                                     => 'Compression :',
		'Open'                                             => 'Ouvrir',
		'Unpack'                                           => 'Décompresser',
		'Unpack to'                                        => 'Décompresser vers',
		'Unpack to folder'                                 => 'Décompresser vers le dossier',
		'Back'                                             => 'Retour',
		'Error while fetching archive info'                => 'Erreur lors de la récupération de informations de l\'archive',
		'Image'                                            => 'Image',
		'MIME-type:'                                       => 'MIME-Type :',
		'Image sizes:'                                     => 'Taille de l\'image :',
		'File'                                             => 'Fichier',
		'Charset:'                                         => 'Charset :',
		'Name'                                             => 'Nom',
		'Size'                                             => 'Taille',
		'Modified'                                         => 'Modifié',
		'Folder'                                           => 'Dossier',
		'Delete'                                           => 'Supprimer',
		'Delete folder?'                                   => 'Supprimer le dossier ?',
		'Delete file?'                                     => 'Supprimer le fichier ?',
		'Rename'                                           => 'Renommer',
		'Copy to...'                                       => 'Copier vers...',
		'File info'                                        => 'Informations',
		'%s byte'                                          => '%s octet',
		'%s KB'                                            => '%s Кb',
		'%s MB'                                            => '%s Мb',
		'%s GB'                                            => '%s Gb',
		'Download'                                         => 'Télécharger',
		'Folder is empty'                                  => 'Dossier vide',
		'Select all'                                       => 'Tout sélectionner',
		'Unselect all'                                     => 'Tout désélectionner',
		'Invert selection'                                 => 'Inverser la sélection',
		'Delete selected files and folders?'               => 'Supprime le fichiers et dossiers sélectionnés ?',
		'Pack'                                             => 'Archiver',
		'Copy'                                             => 'Copier',
		'Upload files'                                     => 'Envoyer des fichiers',
		'New folder'                                       => 'Nouveau dossier',
		'New folder name'                                  => 'Nouveau nom de dossier',
		'New name'                                         => 'Nouveau nom',
		'Operations with archives are not available'       => 'Opérations d\archivage non disponibles',
		'Full size:'                                       => 'Taille totale :',
		'files:'                                           => 'fichiers :',
		'folders:'                                         => 'dossiers :',
		'Perms'                                            => 'Permissions',
		'Username'                                         => 'Nom d\'utilisateur',
		'Password'                                         => 'Mot de passe',
		'Login'                                            => 'Identifiant',
		'Logout'                                           => 'Déconnexion',
		'Wrong password'                                   => 'Mauvais mot de passe',
		'You are logged in'                                => 'Vous êtes connecté',
		'Change Permissions'                               => 'Modifier les permissions',
		'Permissions:'                                     => 'Permissions:',
		'Change'                                           => 'Modifier',
		'Owner'                                            => 'Propriétaire',
		'Group'                                            => 'Groupe',
		'Other'                                            => 'Autre',
		'Read'                                             => 'Lire',
		'Write'                                            => 'Écrire',
		'Execute'                                          => 'Exécuter',
		'Permissions changed'                              => 'Permission changé',
		'Permissions not changed'                          => 'Permission pas changé',
	);
	if (isset($strings[$lang])) {
		return $strings[$lang];
	}
	else {
		return false;
	}
}
