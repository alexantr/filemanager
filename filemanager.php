<?php
/**
 * PHP File Manager
 * Author: Alex Yashkin <alex.yashkin@gmail.com>
 */

# CONFIG

// Use HTTP Auth (set true/false to enable/disable it)
$use_http_auth = false;

// Users for HTTP Auth (user => password)
$users = array(
	'admin' => 'pass123',
);

// Default timezone for date() and time()
$default_timezone = 'Asia/Kuwait'; // UTC+3

// Language (en, ru)
$lang = 'ru';

# END CONFIG

error_reporting(E_ALL);
set_time_limit(600);

date_default_timezone_set($default_timezone);

ini_set('default_charset', 'UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');
if (function_exists('mb_regex_encoding')) mb_regex_encoding('UTF-8');

$start_time = microtime(true);

session_cache_limiter('');
session_name('filemanager');
session_start();

define('DS', '/');

// abs path for site
define('ABS_PATH', $_SERVER['DOCUMENT_ROOT']);

define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);

// HTTP Auth
if ($use_http_auth) {
	$realm = 'Restricted area';

	if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
		header('HTTP/1.1 401 Unauthorized');
		header('WWW-Authenticate: Digest realm="' . $realm . '",qop="auth",nonce="' . uniqid() . '",opaque="' . md5($realm) . '"');
		exit('Restricted area!');
	}

	// analyze the PHP_AUTH_DIGEST variable
	$data = http_digest_parse($_SERVER['PHP_AUTH_DIGEST']);
	if (!$data || !isset($users[$data['username']])) {
		exit('Wrong Credentials!');
	}

	// generate the valid response
	$A1 = md5($data['username'] . ':' . $realm . ':' . $users[$data['username']]);
	$A2 = md5($_SERVER['REQUEST_METHOD'] . ':' . $data['uri']);
	$valid_response = md5($A1 . ':' . $data['nonce'] . ':' . $data['nc'] . ':' . $data['cnonce'] . ':' . $data['qop'] . ':' . $A2);

	if ($data['response'] != $valid_response) {
		exit('Wrong Credentials!');
	}
}

// Show image here
show_image();

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
		$path = ABS_PATH;
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
		$path = ABS_PATH;
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
	$from = ABS_PATH . DS . $copy;
	// abs path to
	$dest = ABS_PATH;
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
	$path = ABS_PATH;
	if ($p != '') $path .= DS . $p;
	// to
	$copy_to_path = ABS_PATH;
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
	$path = ABS_PATH;
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
	$path = ABS_PATH;
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
	$path = ABS_PATH;
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
	$path = ABS_PATH;
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
	$path = ABS_PATH;
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

	$path = ABS_PATH;
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

/*************************** /ACTIONS ***************************/

// get current path
$path = ABS_PATH;
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

		<p><?php _e('Destination folder:') ?> <?php echo $_SERVER['DOCUMENT_ROOT']  . '/' .  $p ?></p>

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

			<p><?php _e('Source folder:') ?> <?php echo $_SERVER['DOCUMENT_ROOT'] ?>/<?php echo $p ?><br>
				<label for="inp_copy_to"><?php _e('Destination folder:') ?></label>
				<?php echo $_SERVER['DOCUMENT_ROOT'] ?>/<input type="text" name="copy_to" id="inp_copy_to" value="<?php echo encode_html($p) ?>">
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
	if ($copy == '' || !file_exists(ABS_PATH . DS . $copy)) {
		set_message(__('File not found'), 'error');
		redirect(BASE_URL . '?p=' . urlencode($p));
	}

	show_header(); // HEADER
	show_navigation_path($p); // current path
	?>
	<div class="path">
		<p><b><?php _e('Copying') ?></b></p>

		<p><?php _e('Source path:') ?> <?php echo $_SERVER['DOCUMENT_ROOT'] ?>/<?php echo $copy ?><br>
			<?php _e('Destination folder:') ?> <?php echo $_SERVER['DOCUMENT_ROOT'] ?>/<?php echo $p ?>
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
<table><tr><th style="width:3%"></th><th style="width:58%"><?php _e('Name') ?></th><th style="width:10%"><?php _e('Size') ?></th><th style="width:12%"><?php _e('Modified') ?></th><th style="width:6%"><?php _e('Perms') ?></th><th style="width:11%"></th></tr>
<?php

			// link to parent folder
			if ($parent !== false) {
				?>
<tr><td></td><td colspan="5"><a href="?p=<?php echo urlencode($parent) ?>"><i class="icon-arrow_up"></i> ..</a></td></tr>
<?php
			}

			foreach ($folders as $f) {
				$modif = date("d.m.y H:i", filemtime($path . DS . $f));
				$perms = substr(sprintf('%o', fileperms($path . DS . $f)), -4);
				?>
<tr><td><label><input type="checkbox" name="file[]" value="<?php echo encode_html($f) ?>"></label></td>
<td><a href="?p=<?php echo urlencode(trim($p . DS . $f, DS)) ?>"><i class="icon-folder"></i> <?php echo $f ?></a></td>
<td><?php _e('Folder') ?></td><td><?php echo $modif ?></td><td><?php echo $perms ?></td><td>
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
				$perms = substr(sprintf('%o', fileperms($path . DS . $f)), -4);
				?>
<tr><td><label><input type="checkbox" name="file[]" value="<?php echo encode_html($f) ?>"></label></td><td>
<?php if (!empty($filelink)) echo '<a href="' . $filelink . '" title="' . __('File info') . '">' ?><i class="<?php echo $img ?>"></i> <?php echo $f ?><?php if (!empty($filelink)) echo '</a>' ?></td>
<td><span title="<?php printf(__('%s byte'), $filesize_raw) ?>"><?php echo $filesize ?></span></td>
<td><?php echo $modif ?></td><td><?php echo $perms ?></td><td>
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

	$path = ABS_PATH;
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
	global $p;
	?>
<div class="path">
<div class='float-right'>
<a title="<?php _e('Upload files') ?>" href="?p=<?php echo urlencode($p) ?>&amp;upload"><i class="icon-upload"></i></a>
<a title="<?php _e('New folder') ?>" href="#" onclick="newfolder('<?php echo encode_html($p) ?>');return false;"><i class="icon-folder_add"></i></a>
</div>
		<?php
		$path = clean_path($path);
		$root_url = "<a href='?p='><i class='icon-home' title='{$_SERVER['DOCUMENT_ROOT']}'></i></a>";
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
a{color:#296ea3;text-decoration:none}a:hover{color:#b00}img{vertical-align:middle;border:none}
a img{border:none}span{color:#777}small{font-size:11px;color:#999}p{margin-bottom:10px}
ul{margin-left:2em;margin-bottom:10px}ul{list-style-type:none;margin-left:0}ul li{padding:3px 0}
table{border-collapse:collapse;border-spacing:0;margin-bottom:10px;width:100%}
th,td{padding:4px 7px;text-align:left;vertical-align:top;border:1px solid #ddd;background:#fff;white-space:nowrap}
th,td.gray{background-color:#eee}td.gray span{color:#222}tr:hover td{background-color:#f5f5f5}
code,pre{display:block;margin-bottom:10px;font:13px/16px Consolas,'Courier New',Courier,monospace;border:1px dashed #ccc;padding:5px;overflow:auto}
code.maxheight,pre.maxheight{max-height:512px}input[type="checkbox"]{margin:0;padding:0}
#wrapper{max-width:900px;min-width:400px;margin:10px auto}
.path{padding:4px 7px;border:1px solid #ddd;background-color:#fff;margin-bottom:10px}
.right{text-align:right}.center{text-align:center}.float-right{float:right}
.message{padding:4px 7px;border:1px solid #ddd;background-color:#fff}
.message.ok{border-color:green;color:green}.message.error{border-color:red;color:red}.message.alert{border-color:orange;color:orange}
.btn{border:0;background:none;padding:0;margin:0;font-weight:bold;color:#296ea3;cursor:pointer}.btn:hover{color:#b00}
.preview-img{max-width:100%;background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAKklEQVR42mL5//8/Azbw+PFjrOJMDCSCUQ3EABZc4S0rKzsaSvTTABBgAMyfCMsY4B9iAAAAAElFTkSuQmCC") repeat 0 0}
[class*="icon-"]{display:inline-block;width:16px;height:16px;background:url("?img=sprites") no-repeat 0 0;vertical-align:bottom}
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
</style>
<link rel="icon" href="?img=favicon" type="image/png">
<link rel="shortcut icon" href="?img=favicon" type="image/png">
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
	global $start_time;
	?>
<p class="center"><small>PHP File Manager [<?php echo round((microtime(true) - $start_time), 4) ?>]</small></p>
</div>

<script>
function newfolder(p){var n=prompt('<?php _e('New folder name') ?>','folder');if(n!==null&&n!==''){window.location.search='p='+encodeURIComponent(p)+'&new='+encodeURIComponent(n);}}
function rename(p,f){var n=prompt('<?php _e('New name') ?>',f);if(n!==null&&n!==''&&n!=f){window.location.search='p='+encodeURIComponent(p)+'&ren='+encodeURIComponent(f)+'&to='+encodeURIComponent(n);}}
function change_checkboxes(l,v){for(var i=l.length-1;i>=0;i--){l[i].checked=(typeof v==='boolean')?v:!l[i].checked;}}
function get_checkboxes(){var i=document.getElementsByName('file[]'),a=[];for(var j=i.length-1;j>=0;j--){if(i[j].type='checkbox'){a.push(i[j]);}}return a;}
function select_all(){var l=get_checkboxes();change_checkboxes(l,true);}
function unselect_all(){var l=get_checkboxes();change_checkboxes(l,false);}
function invert_all(){var l=get_checkboxes();change_checkboxes(l);}
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
		'sprites' => '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
nOIAAAAASUVORK5CYII=',
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
		'Invert selection'                                 => 'Инвертировать',
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
	);
	if (isset($strings[$lang])) {
		return $strings[$lang];
	}
	else {
		return false;
	}
}