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
		set_message(sprintf(__('All files uploaded to <b>%s</b>'), $p));
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

		<p><?php _e('Destination folder:') ?> <?php echo $_SERVER['DOCUMENT_ROOT']  . '/' .  $p; ?></p>

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
				<button type="submit" class="btn"><img src="?img=apply" alt=""> <?php _e('Upload') ?></button> &nbsp;
				<b><a href="?p=<?php echo urlencode($p) ?>"><img src="?img=cancel" alt=""> <?php _e('Cancel') ?></a></b>
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

			<p><?php _e('Source folder:') ?> <?php echo $_SERVER['DOCUMENT_ROOT'] ?>/<?php echo $p; ?><br>
				<?php _e('Destination folder:') ?> <?php echo $_SERVER['DOCUMENT_ROOT'] ?>/<input type="text" name="copy_to" value="<?php echo encode_html($p) ?>">
			</p>

			<p><label><input type="checkbox" name="move" value="1"> <?php _e('Move') ?></label></p>
			<p>
				<button type="submit" class="btn"><img src="?img=apply" alt=""> <?php _e('Copy') ?></button> &nbsp;
				<b><a href="?p=<?php echo urlencode($p) ?>"><img src="?img=cancel" alt=""> <?php _e('Cancel') ?></a></b>
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

		<p><?php _e('Source path:') ?> <?php echo $_SERVER['DOCUMENT_ROOT'] ?>/<?php echo $copy; ?><br>
			<?php _e('Destination folder:') ?> <?php echo $_SERVER['DOCUMENT_ROOT'] ?>/<?php echo $p; ?>
		</p>

		<p>
			<b><a href="?p=<?php echo urlencode($p) ?>&amp;copy=<?php echo urlencode($copy) ?>&amp;finish=1"><img src="?img=apply" alt=""> <?php _e('Copy') ?></a></b> &nbsp;
			<b><a href="?p=<?php echo urlencode($p) ?>&amp;copy=<?php echo urlencode($copy) ?>&amp;finish=1&amp;move=1"><img src="?img=apply" alt=""> <?php _e('Move') ?></a></b> &nbsp;
			<b><a href="?p=<?php echo urlencode($p) ?>"><img src="?img=cancel" alt=""> <?php _e('Cancel') ?></a></b>
		</p>

		<p><i><?php _e('Select folder:') ?></i></p>
		<ul class="folders">
			<?php
			if ($parent !== false) {
				?>
				<li><a href="?p=<?php echo urlencode($parent) ?>&amp;copy=<?php echo urlencode($copy) ?>"><img src="?img=arrow_up" alt=""> ..</a></li>
			<?php
			}
			foreach ($folders as $f) {
				?>
				<li><a href="?p=<?php echo urlencode(trim($p . DS . $f, DS)) ?>&amp;copy=<?php echo urlencode($copy) ?>"><img src="?img=folder" alt=""> <?php echo $f ?></a></li>
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
		<p><b><?php _e('Archive') ?> <?php echo $file; ?></b></p>
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
				<b><a href="<?php echo $file_url ?>" target="_blank"><img src="?img=folder_open" alt=""> <?php _e('Open') ?></a></b> &nbsp;
				<b><a href="?p=<?php echo urlencode($p) ?>&amp;unzip=<?php echo urlencode($file) ?>"><img src="?img=apply" alt=""> <?php _e('Unpack') ?></a></b> &nbsp;
				<b><a href="?p=<?php echo urlencode($p) ?>&amp;unzip=<?php echo urlencode($file) ?>&amp;tofolder=1" title="<?php _e('Unpack to') ?> <?php echo encode_html($zip_name) ?>"><img src="?img=apply" alt="">
						<?php _e('Unpack to folder') ?></a></b> &nbsp;
				<b><a href="?p=<?php echo urlencode($p) ?>"><img src="?img=goback" alt=""> <?php _e('Back') ?></a></b>
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
			echo '<p>' . __('Error while fetching archive info') . '</p>';
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
		<p><b><?php _e('Image') ?> <?php echo $file; ?></b></p>

		<p>
			<?php _e('Full path:') ?> <?php echo $file_path ?><br>
			<?php _e('File size:') ?> <?php echo get_filesize(filesize($file_path)) ?><br>
			<?php _e('MIME-type:') ?> <?php echo isset($image_size['mime']) ? $image_size['mime'] : get_mime_type($file_path) ?><br>
			<?php _e('Image sizes:') ?> <?php echo (isset($image_size[0])) ? $image_size[0] : '0' ?> x <?php echo (isset($image_size[1])) ? $image_size[1] : '0' ?>
		</p>

		<p>
			<b><a href="<?php echo $file_url ?>" target="_blank"><img src="?img=folder_open" alt=""> <?php _e('Open') ?></a></b> &nbsp;
			<b><a href="?p=<?php echo urlencode($p) ?>"><img src="?img=goback" alt=""> <?php _e('Back') ?></a></b>
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
		<p><b><?php _e('File') ?> <?php echo $file; ?></b></p>

		<p>
			<?php _e('Full path:') ?> <?php echo $file_path ?><br>
			<?php _e('File size:') ?> <?php echo get_filesize(filesize($file_path)) ?><br>
			<?php _e('MIME-type:') ?> <?php echo get_mime_type($file_path) ?><br>
			<?php _e('Charset:') ?> <?php echo ($is_utf8) ? 'utf-8' : 'windows-1251' ?>
		</p>

		<p>
			<b><a href="<?php echo $file_url ?>" target="_blank"><img src="?img=folder_open" alt=""> <?php _e('Open') ?></a></b> &nbsp;
			<b><a href="?p=<?php echo urlencode($p) ?>"><img src="?img=goback" alt=""> <?php _e('Back') ?></a></b>
		</p>

		<?php echo $content; ?>
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

?>
	<form action="" method="post">
		<input type="hidden" name="p" value="<?php echo encode_html($p) ?>">
		<input type="hidden" name="group" value="1">
		<table>
			<tr>
				<th style="width: 3%"></th>
				<th style="width: 60%"><?php _e('Name'); ?></th>
				<th style="width: 11%"><?php _e('Size'); ?></th>
				<th style="width: 14%"><?php _e('Modified'); ?></th>
				<th style="width: 12%"></th>
			</tr>
			<?php

			// link to parent folder
			if ($parent !== false) {
				?>
				<tr>
					<td></td>
					<td colspan="4">
						<a href="?p=<?php echo urlencode($parent) ?>"><img src="?img=arrow_up" alt=""> ..</a>
					</td>
				</tr>
			<?php
			}

			foreach ($folders as $f) {
				$modif = date("d.m.y H:i", filemtime($path . DS . $f));
				?>
				<tr>
					<td>
						<input type="checkbox" name="file[]" value="<?php echo encode_html($f) ?>">
					</td>
					<td>
						<a href="?p=<?php echo urlencode(trim($p . DS . $f, DS)) ?>"><img src="?img=folder" alt="">
							<?php echo $f ?></a>
					</td>
					<td><?php _e('Folder') ?></td>
					<td><?php echo $modif ?></td>
					<td>
						<a title="<?php _e('Delete') ?>" href="?p=<?php echo urlencode($p) ?>&amp;del=<?php echo urlencode($f) ?>" onclick="return confirm('<?php _e('Delete folder?') ?>');"><img src="?img=cross" alt=""></a>
						<a title="<?php _e('Rename') ?>" href="#" onclick="rename('<?php echo encode_html($p) ?>', '<?php echo encode_html($f) ?>');return false;"><img src="?img=rename" alt=""></a>
						<a title="<?php _e('Copy to...') ?>" href="?p=&amp;copy=<?php echo urlencode(trim($p . DS . $f, DS)) ?>"><img src="?img=copy" alt=""></a>
					</td>
				</tr>
			<?php
				flush();
			}

			foreach ($files as $f) {
				$img = get_file_icon($path . DS . $f);
				$modif = date("d.m.y H:i", filemtime($path . DS . $f));
				$filesize = get_filesize(filesize($path . DS . $f));
				$filelink = get_file_link($p, $f);
				?>
				<tr>
					<td>
						<input type="checkbox" name="file[]" value="<?php echo encode_html($f) ?>">
					</td>
					<td>
						<?php if (!empty($filelink)) echo '<a href="' . $filelink . '" title="' . __('File info') . '">'; ?>
							<img src="<?php echo $img ?>" alt=""> <?php echo $f ?>
						<?php if (!empty($filelink)) echo '</a>'; ?>
					</td>
					<td><span title="<?php printf(__('%s byte'), filesize($path . DS . $f)) ?>"><?php echo $filesize ?></span></td>
					<td><?php echo $modif ?></td>
					<td>
						<a title="<?php _e('Delete') ?>" href="?p=<?php echo urlencode($p) ?>&amp;del=<?php echo urlencode($f) ?>" onclick="return confirm('<?php _e('Delete file?') ?>');"><img src="?img=cross" alt=""></a>
						<a title="<?php _e('Rename') ?>" href="#" onclick="rename('<?php echo encode_html($p) ?>', '<?php echo encode_html($f) ?>');return false;"><img src="?img=rename" alt=""></a>
						<a title="<?php _e('Copy to...') ?>" href="?p=<?php echo urlencode($p) ?>&amp;copy=<?php echo urlencode(trim($p . DS . $f, DS)) ?>"><img src="?img=copy" alt=""></a>
						<a title="<?php _e('Download') ?>" href="?p=<?php echo urlencode($p) ?>&amp;dl=<?php echo urlencode($f) ?>"><img src="?img=download" alt=""></a>
					</td>
				</tr>
			<?php
				flush();
			}

			if (empty($folders) && empty($files)) {
				?>
				<tr>
					<td></td>
					<td colspan="4"><em><?php _e('Folder is empty') ?></em></td>
				</tr>
			<?php
			}

			?>
		</table>
		<p class="path">
			<a href="#" onclick="select_all();return false;"><img src="?img=checkbox" alt=""> <?php _e('Select all') ?></a> &nbsp;
			<a href="#" onclick="unselect_all();return false;"><img src="?img=checkbox_uncheck" alt=""> <?php _e('Unselect all') ?></a> &nbsp;
			<a href="#" onclick="invert_all();return false;"><img src="?img=checkbox_invert" alt=""> <?php _e('Invert selection') ?></a>
		</p>
		<p>
			<input type="submit" name="delete" value="<?php _e('Delete') ?>" onclick="return confirm('<?php _e('Delete selected files and folders?') ?>')">
			<input type="submit" name="zip" value="<?php _e('Pack') ?>">
			<input type="submit" name="copy" value="<?php _e('Copy') ?>">
		</p>
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
			$img = '?img=file_image'; break;
		case 'txt': case 'css': case 'ini': case 'conf': case 'log': case 'htaccess':
		case 'passwd': case 'ftpquota': case 'sql': case 'js': case 'json': case 'sh':
		case 'config': case 'twig': case 'tpl':
			$img = '?img=file_text'; break;
		case 'zip': case 'rar': case 'gz': case 'tar': case '7z':
		$img = '?img=file_zip'; break;
		case 'php': case 'php4': case 'php5': case 'phps': case 'phtml':
		$img = '?img=file_php'; break;
		case 'htm': case 'html': case 'shtml': case 'xhtml':
		$img = '?img=file_html'; break;
		case 'xml': case 'xsl':
		$img = '?img=file_code'; break;
		case 'wav': case 'mp3': case 'mp2': case 'm4a': case 'aac': case 'ogg':
		case 'oga': case 'wma': case 'mka': case 'flac': case 'ac3': case 'tds':
		$img = '?img=file_music'; break;
		case 'm3u': case 'm3u8': case 'pls': case 'cue':
		$img = '?img=file_playlist'; break;
		case 'avi': case 'mpg': case 'mpeg': case 'mp4': case 'm4v': case 'flv':
		case 'f4v': case 'ogm': case 'ogv': case 'mov': case 'mkv': case '3gp':
		case 'asf': case 'wmv':
		$img = '?img=file_film'; break;
		case 'eml': case 'msg':
		$img = '?img=file_outlook'; break;
		case 'xls': case 'xlsx':
		$img = '?img=file_excel'; break;
		case 'csv':
			$img = '?img=file_csv';  break;
		case 'doc': case 'docx':
		$img = '?img=file_word'; break;
		case 'ppt': case 'pptx':
		$img = '?img=file_powerpoint'; break;
		case 'ttf': case 'ttc': case 'otf': case 'woff': case 'eot': case 'fon':
		$img = '?img=file_font'; break;
		case 'pdf':
			$img = '?img=file_pdf'; break;
		case 'psd':
			$img = '?img=file_photoshop'; break;
		case 'ai': case 'eps':
		$img = '?img=file_illustrator'; break;
		case 'fla':
			$img = '?img=file_flash'; break;
		case 'swf':
			$img = '?img=file_swf'; break;
		case 'exe': case 'msi':
		$img = '?img=file_application'; break;
		case 'bat':
			$img = '?img=file_terminal'; break;
		default:
			$img = '?img=document';
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
			<a title="<?php _e('Upload files') ?>" href="?p=<?php echo urlencode($p) ?>&amp;upload"><img src="?img=upload" alt=""></a>
			<a title="<?php _e('New folder') ?>" href="#" onclick="newfolder('<?php echo encode_html($p) ?>');return false;"><img src="?img=folder_add" alt=""></a>
		</div>
		<?php
		$path = clean_path($path);
		$root_url = "<a href='?p='><img src='?img=home' title='{$_SERVER['DOCUMENT_ROOT']}' alt=''></a>";
		$sep = '<img src="?img=separator" alt="»">';
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
		html{overflow-y:scroll}
		body{padding:0;font:13px/16px Arial,sans-serif;color:#333;background:#efefef}
		a{color:#296ea3;text-decoration:none}
		a:hover{color:#b00}
		img{vertical-align:bottom;border:none}
		a img{border:none}
		span{color:#777}
		small{font-size:11px;color:#999}
		p{margin-bottom:10px}
		ul{margin-left:2em;margin-bottom:10px}
		ul{list-style-type:none;margin-left:0}
		ul li{padding:3px 0}
		table{border-collapse:collapse;border-spacing:0;margin-bottom:10px;width:100%}
		th,td{padding:4px 7px;text-align:left;vertical-align:top;border:1px solid #ddd;background:#fff;white-space:nowrap}
		th{background-color:#eee}
		tr:hover td{background-color:#f5f5f5}
		code,pre{display:block;margin-bottom:10px;font:13px/16px Consolas,'Courier New',Courier,monospace;border:1px dashed #ccc;padding:5px;overflow:auto}
		code.maxheight,pre.maxheight{max-height:512px}
		input[type="checkbox"]{margin:0;padding:0}
		#wrapper{max-width:800px;min-width:400px;margin:10px auto}
		.path{padding:4px 7px;border:1px solid #ddd;background-color:#fff;margin-bottom:10px}
		.right{text-align:right}
		.center{text-align:center}
		.float-right{float:right}
		.message{padding:4px 7px;border:1px solid #ddd;background-color:#fff}
		.message.ok{border-color:green;color:green}
		.message.error{border-color:red;color:red}
		.message.alert{border-color:orange;color:orange}
		.btn{border:0;background:none;padding:0;margin:0;font-weight:bold;color:#296ea3;cursor:pointer}
		.btn:hover{color:#b00}
		.preview-img{max-width:100%;background:url("data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAIAAACQkWg2AAAAKklEQVR42mL5//8/Azbw+PFjrOJMDCSCUQ3EABZc4S0rKzsaSvTTABBgAMyfCMsY4B9iAAAAAElFTkSuQmCC") repeat 0 0}
		img[src*="?img="]{width:16px;height:16px}
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
<p class="center"><small>PHP File Manager [<?php echo round((microtime(true) - $start_time), 4); ?>]</small></p>
</div>

<script>
	function newfolder(p) {
		var name = prompt('<?php _e('New folder name') ?>', 'folder');
		if (name !== null && name !== '') {
			window.location.search = 'p=' + encodeURIComponent(p) + '&new=' + encodeURIComponent(name);
		}
	}
	function rename(p, f) {
		var name = prompt('<?php _e('New name') ?>', f);
		if (name !== null && name !== '' && name != f) {
			window.location.search = 'p=' + encodeURIComponent(p) + '&ren=' + encodeURIComponent(f) + '&to=' + encodeURIComponent(name);
		}
	}
	function change_checkboxes(list, value) {
		for (var i = list.length - 1; i >= 0; i--) {
			list[i].checked = (typeof value === 'boolean') ? value : !list[i].checked;
		}
	}
	function get_checkboxes() {
		var inputs = document.getElementsByName('file[]');
		var all_checkboxes = [];
		for (var j = inputs.length - 1; j >= 0; j--) {
			if (inputs[j].type = 'checkbox') {
				all_checkboxes.push(inputs[j]);
			}
		}
		return all_checkboxes;
	}
	function select_all() {
		var list = get_checkboxes();
		change_checkboxes(list, true);
	}
	function unselect_all() {
		var list = get_checkboxes();
		change_checkboxes(list, false);
	}
	function invert_all() {
		var list = get_checkboxes();
		change_checkboxes(list);
	}
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
		'document' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAV9JREFUeNqMkrFOwzAQhs9OAgzkAWBnad8izKzwCrxFq0qVeAAkOlfq
hthRJV6hGdhg654IJRFVYzvcXWMrEDfipPNZ5/Pn+22LpmmA7HY6vcFwAcP2Zoz5NFrDy3zOidCu
4MLlcjJZ7HGxJjcGNMLtAWmawnK9vsepQP+w+6QDaC0Mbapr9v1uB99VBWVZQlEUHJ9nswXWXWP5
VQ+gEUBoiWMUBHAWRXAShhBKCQJzWZbB42oFyWj09L7Z3Nl9YacDSZslY4AhzpSCJElYThzH8Jqm
eQ+gqIMOwAdRKNHW9gBaKSeha38hdAjV+gAHCagZ2pN8kOAAkH0JmHQdDEACjOoIgO+A74Hfxw8J
MK+8EuradWDs+3ogLAFr/RKoA3yqIYhoa/0SbAcDEMp7JeC35bm9g2MQ0an9BfjK81PXZvuh6OeJ
NjKUvjV6t9YBqrLcno/HD/APQ8jWzn8EGACxU8j1qPzZewAAAABJRU5ErkJggg==',
		'folder' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAVtJREFUeNqMkz1OAzEQhZ/tXURQWnooKCiogAtwAc6QBrq0SEgUiIqC
hoaCghNQUKSHGuUAJE0K2kj8LMlm1z/MeJNAknU2lqyR7Zm33xt7hXMOPNrXokFhC6uNHlU9HJw5
RJMd67B92Hy6gDHLS5XC6+3x1WQ5FSAQCZ3D5fnSehHHRe68gLGQlr7uKgiElD53QYAsKLeCAEiA
c0sJuNgaXVEfIKBNZU0Ooyt6EEU+d3x5cwKaCHSYIKpt4P2t43Npuc+3L/8LOMK3gSnp+riYG310
aZuU3yghyGEDFix5//74xM7eLgZZNqH4EyB6xfgmYEFKRW+ssJikqc8vaSLjBgiMgtaFnZ/RaEow
1wPjPZZNPjPjeNLbXLSQZlh3NvyQ+CwldI79/sDnzwh8DVGXQiKO4vIe0FmSJD6ePtZbicDdjMAw
Q/f+5vy56j/utFsQAt3aGl54/SvAAHvn9T8uehsGAAAAAElFTkSuQmCC',
		'folder_add' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAeVJREFUeNqMk89LG0EUx7/7w2CqSA0k0oJQCz1UKCLqpUVQD3opFDwV
CgUvFTwEcioePEgvHjyIFxEKRcRjDx4MFRT/gChYsJT0FEqhF5WErOlmd2aeb3ZNtmpMHHgMu+z3
M9/3nbdGbgnBMoAZ3p7gfqtAwJfhjwSbKHzDW99IensBUjaXWhZyq28+1R7rAF4mhA/y/aZ6o60N
rDHrAKkigOLTqYUDwzTBmgigIgcW3QMABrDGauhAi5UULfQ3HPwHsJT0IUWLDGxbA6xadtcBgh2I
ux3Y8Qf4k/8VAPhxiOvI1ICr4gxE0EKjMvn6tFgHPbGo0vz9TADdfBhHB/PyHfb4/I81DLT3YOXR
5K3TFfdeLpbw7MVzVDyv5gL2ViKOGEeSIEq9fjWF3EEWMikahGfxjIUtOq4L7tYKh4ew7ikcwiOj
JEuAxxZ/biBT+AYdalQCQoTtXFSrkQNU1dD49IQOBKfiFImx/uDE77snUI+jmdBXLK/m5EMhibk6
wFVHB1t76PHV0+S7wW4z+zcQDHSmrg0VKQmXrev97KwC10N7CCDMxnwF+qeK5/IcqSph/+X72xkY
JhzHCfbZr507joG18C9e7kLqt8v3iCwEjfYSld9WRL7pMBk4jseQmftMuBRgAIJcEBH4t7sLAAAA
AElFTkSuQmCC',
		'upload' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACRElEQVR42oxTO2hTURj+7iu5tiSS
RlK1CEWJGTLJHRJRO/igGRQNDg6CoIuDk9ndMji5CZpF0G6OLiX1AaU2GkiHQhYL2gaTSDS5NuU+
c2+u549pyUvwh++ew//4zv+6nOd5IDl+5QEOn78LeXoasixDYhBEcYFsruOsdkwTJkHTsLv2ArWV
p704Hn0JnL2DIfG8FAt8QqD7oGnQV8QEYVmlOpaVvX11XrFtD6/zO9l+psujvvykYIsF37x8QqlW
DVQqOhaTxxTSeSOZjBH0gnU9eyt1ir3sQtN01Gp1hp84c1JWyDZKclCCY9tEkKZUn78slEh3fTGm
dFwHK2tfS/t+HMelGZYn9oAF3++6LrrdLhF+bDm8uSvJ1HlL9PnO8TwPXhCIZDyDo81P2Ds0hybC
0NttUm3VOVmoywIRfGH1YCoYRLjzAwGjyswXhglmmwXMiSJa3BGsI0mqjSo/1Wj4PViG8Z0UivEB
M94vOI7z7zGSQ9Jbx2pH2VQ9saIJrD+W9XtBKmGGa42NfJAgwHpwjc4wKyQt5YFc/q9Fwiz7xvpL
u8fwpn8OTMFxbmQymUfBYDBqmgYMQ2cwmd6l3vdYAB90Xd9aWnrG8sKrIQJmCCUSiShNgAho79ny
DBCIUFUNgUAomsvpobESNE2TVFVFoVBAuVyGbVsMNhspei8DfqiPd3Dv88Oe7xhBu932F4vF7Xg8
Ph+Lne5lYVn2UAbGWxeb3za2yfdgsfZ/50gkctF13Uv4DxEE4V2j0XhP9z8CDABctzsurHMrigAA
AABJRU5ErkJggg==',
		'arrow_up' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAfZJREFUeNqck0FrE0EUgN9kJkNsQ0CFQsEavAheXfEqeDARUagXDeq5
f8CTeAx48eJVPBevgkIPlv4ATSmCRGI9CAEbt9XdpruZndmZ8c3WbJO4ufjgMfPezPtm5r03xFoL
Ti4/7wLjHFi5nCmltInuNupTrfVGqhRkKiVsP74EYylBsTQxqN1qLHludPacfYWAJp7UfnBz2ev3
FbQay56z50GmAdZmwQ9vrXi9XgRRJGF3N4JW89wxBNfnAuzf4Ee3L3h7ewIIsRDHyq3AYCDgXqOe
QewMhI0nWqlVl9CX6586zr5z/aI3HFrgnMCbzV5nvI8QsorDxj8ADF6zxrgRMHEfpVQQhiHaDBRm
HqtyBYPxztOvZpOG0RoSMcrmvr8PBwcBMFYDlQhIjM3KW1lYKAaIKAIRx/mFRoaAZGUQlmDtVckY
w7AHUrevEDAMgkm/HxgqD/kip4S7u1F8Rg0nv2arkANUkkz6u34orv6unj6bUHwzp1yFyVIRIM9I
3qrH+rr/+dsPXanoAV0EXa2ewgPqUmAuTp45DdBpeqJKfTga7K9HnZ3vaamkRyvnzyD0hvsHDlIM
wJNzRQhW5MXRl6+v5Nt3XRUcJrZWu4b+J65Sc8s4IzH2xTP1038vN7fuo+2+4F3shDqOa3ljjb/z
/8ofAQYACMkx7hge4jEAAAAASUVORK5CYII=',
		'home' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAoJJREFUeNp8U19IU2EUP/fe766LYylstR60DCspJRiaUWtW9k9kD2OG
hRBZD6mEUVGQQpk0GhaMYIT1IvnQQ0QkVA8WPpT/YCDVQyx6yEAa1UTTnN7dK9zO+bZ9KkQHfru/
e87vd75zPu6k9QAgESQp88wCYx/iYobCvaJ1MFzksqqRuxBTiHdUYPDvIOGlSE9PgF4ut7Yqk0mQ
zzX5y093PLjfd7vl/P8a7LcArtyNRv2VlSU8cScaDVxta2PxidlfqdkpmJ9fUHNixb56hYNIr4Uj
kTqvd0cmhygsdMLW7Z5t7+OTnnxHHkzEY4O6YYwiVk1wCE/uuNXdXePzlcPYozDkV53ghdnYE/A1
tYNpmtDX9wzWmHMVyeQMWCtWOGJZ1vUboZCvpsYDqqpC6uc3KPM18GLiRTfPUc3AU18PDJz8EP/c
iw3eUIOjSG52dHbuqa3dDZqmiZEK8myCK4rCQRqaZGxoqIs2l/EntJaxtyNPu5rdbrcQ2t3F8D32
ioN4Lk8a0pKHvAxPryre6IJg/d4WWZa5iOLYhbA4vbR0mZMmWB+UZx6Ptn/8+gPkEjSfaqgCw1yS
ZTQzxji+xIYhcibAQTyXJw1pyUNedvb4LkgbJm8uyRkRxfjzXgj6vZyPId9ZfZhz0qCeVgfyMj1t
iHtauYJlLPAbdzgcsGXTBpEnDXoUsdKinoYssIEiLkvTbKDrOkxPz0F//6DIk4a0OR9bRFFuAinb
gELTVN7A6bRDY2OdyEu8gS4mYPOpRcElZsOLyPwXbTYV0uk0JBIJfBog2/IyDRQV0CO+YOnA8qf8
EFFB5DfAyz+MlSGdyNY2O5aWPhUA+LPv44hmIn8FGACVEehokZL75AAAAABJRU5ErkJggg==',
		'separator' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAJpJREFUeNpi/P//PwMlgImBQjDwBrDgkrCf+2ImkDr868ePJd8+f2a4
WKFLmgFAjcZAyhgayEtI9gLI1sRABePvX74U/P/3L4ZkA75++shw6zMDQ1CYrvG3L58LSA6Drx8/
Mdz+wsBwat62s4xMTBPIcMEnhv0TVpz99uXLBKAXlpDsgp9fv54FxQI7N/cSfNHIOJqUKTcAIMAA
amxFmNUyJj0AAAAASUVORK5CYII=',
		'cross' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAhFJREFUeNpi/P//PwMlgImBQsACY6xiZPQAUi1Q7vF/DAy5v4GMH1BF
bBBam5GBIRjINALibWH//8+CGwBU3BJRUmL84/v3/zdPn5a8fuqUJdBzJjB5ILuTgY3N7fevX++A
hvQChbaDxBlhYbCAkTENyErTNjeXVNTWlvr57dvvM/v23Xn96lUsPx9fjqiUlN2DGzeuAzVnszIw
PATSDFFAvXAD5jMyMvxiYDAGOj0cyHUyd3RU4hEQ4L937dozdg4OhhsXL54DOjcdqPkFK8hmHAaA
/fwXaBBQwUw9Q0N1BiYm9tvXrt36/v27DiPEK2AgDjUAHgaPENEiChSsVZKSkv7x8SPbradPbwjy
8vJ/+/Pn0pffv/VAlvwB4lfo0fgDEpAgyRoFUVFTMWZmsWtPn97++PPnxPtv3pxlZ2XlAspt+4cr
HYAMAJqeLsHL663GxCT15NOnF29//lwIdPJcoMagR9++beVhYZEHstt+Q9SiGgD0t/xPBoZUaw4O
+e+/fn098/HjeSB/zieg3D+Iy/Kf/PmzFmiREVCzI4YBQMW5hlxckjx//7Js/fTpPpBfD1T0HqYQ
akgd0IAzQDmPH9BEiGyAgw07u+SlX7/ePv/7dwdQ49nvQPHvEDm4IUCX1gD5r4De0ENJSNHApPwf
kpTfMgPDAhhlD5BSIQNa4CkCxSSW/f9/nHHAcyNAgAEAxF3igbzwV7kAAAAASUVORK5CYII=',
		'copy' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAdZJREFUeNqMk79LAmEYx59771T8QWtkjgmR4FgGLlIgCOVWQ3vRfxC0
CC5Ba+jQFOHY6OLQFDQJFyQhRQ6C43mmgnq/ep7Xu+s8HXrh8dXnnufzfr++zwmWZcFJqXQEABuw
ej0bhvFlGgYEgkGemLZeQP94hfr7GCRKYMHmY6lUneo6zEwTNCw2ESzLMjw0GpdYImB8OkRpex9Y
cpd/Z/SBdMHCRhMB5mwGs8kExuMxjEYjeCqXq/j8EMuSq+RxgK7rAi4QMYKSBN/dLry129DqdOC6
UoGdRKIy6PdvsfQcY8sLkGwAY9jMGPJQyRBPP85mIZ/JuJYuLKuIlop+S3MFmkatXAGHoP//WnIB
gt1MEPoDvZbCgQCEcFcUBe5qNThIpSotWT79s0AKCIABtg2vJYLQKuTzQDcVjUah3myqLkAjBbYF
fisEcH77IFw25ujQRQAWc9k2QPAo8EOWAdMpV0CyzflcgN+SFyKK4iJAVZQQ7YLdhJMJXkt+COU1
L+BHVXtr6fSN43E9Ht87KxRyjiU/hNm2XQB6vsdh4nXhSITiCs/OOZb8EMqTbRfgjiXedzQWW7JE
byyZofmwCILh1CwARHwwHAyoYcHSypeIsR7tvwIMAHNYCdW2UN0MAAAAAElFTkSuQmCC',
		'apply' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAehJREFUeNpi/P//PwMlgImBQsACIhhnCAFZjEAGkANy0B8gwQ00m5kR
ogpEMYHl5YF4LZBXw/D3/47/Ea/xuOD7PwaGX1AMMvDvfwGGf/8nprkkGQPZLUAVHqR4gY3hH0Of
oby+6ZcvXxjinWL0Gf4wtMC9QAAwAjXXK0jIO8gLyUl9/Pzx98Fjh24AXdOA7gIPoP/PwJwGdvoP
oNP/MGQIcwkGq4mrKHz5+uXf0UtH7wBdMRGodgvCgP9ATX/+tyT5JxqCaDAfIu7DwcSWJckjqfzg
wQOGU5dP3f3w4cNSoJq5DL//I0Xjn38tMX4xes+ePWOK8IowAPGBmnNZ/jPVS4vLqH7985Xl5YcX
Dz99+rAJGIDtQAxxIcKA/zVLViy8xM7J9uvU7VPMDnaOOkAb4sVkxTV+sPxgf/fhzdOP797vZ/gL
jD4Ghn8oAQRKiYx9/AxADaAwaDF2NtN6+vMZpwCnAMP7b+8Zfrz49vrj3fdHGJgZkhhYmT4wsELT
C8iHWe+RAvE/ww6g02vO7jhxjfkv49fXjK8Zvn/5+uHTzXcXgSGeC1TxAWdKRDHk81+Gp5vutnCZ
8Mt8v/DpDtCBeUBrnuJNyigAmEQZ3v9h+LbzTQsw+ZYwCLJcBydjXIlkwHMjQIABAIHQ3hY9qLek
AAAAAElFTkSuQmCC',
		'cancel' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAX5JREFUeNrEU71OwlAUPrVqrDFBHsARX6AJri4kJSEyEBMHggkLK8EE
FsauvoPo4iN04gkgLiYOOBHUxDSUCBO0XL9z7a1t1YnBJl967/l+enIOaEII2uTZog2fbXW41zTS
iSwcbaAbEDl+TJTmLsLO4x1YIOzzTscMvoRWmjtrNn9yPAPGHdFg3W6Lx1JJLFstwXfACjF4r9cl
N6lWJad8UUAPwhsQXqMh+vm8eK3VBN8ZT+WyrA0LBVljrfJpags9zMBnAi2eVirm82hEmUyGFosF
vUwmZBgGzcfjoYYZYCbOZehLBKzwDsKQo1zOnLqu5HRdpw/XlWYM09lBTQVEW3gDdsMChLSazWhf
kUEgw9nCm1n+9TuAwILAPs5mTcP36QA1Ba4xJ5LbSa4RX7FPIFSmB88bMtSduVVqjVEAE8WYuQ8j
vthl9GMhxe+QZMAcwlsI93DmN9/RrsP4jVO+aAtX2IKH1tZIR2o3C+NhKJoBae46vcZ/+zd+CjAA
N5vaL1x3kMMAAAAASUVORK5CYII=',
		'rename' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJ
bWFnZVJlYWR5ccllPAAAAUNJREFUeNrEUz1OhFAQnkceEFiIJtLQGBOOoIUNl0AsvIGJpYkVBVp5
BKmxE2m8ATQWeghMtMKN2WzkJwScwSXZisVs4SRf3geZ72NmmMe6roNtQoAtgymKAlVVjSZRlYgj
pIeI17XzBchAEIRJYIyR3wWd9Exapuv6WdM0+1NLLoqiQqFMnHP+xi3LMuM4vp1qEEURuK7bc8dx
Lrlpmo0oinAdKrBYfEGe5zCff8Jy+Y19c5DlHZjNDFDVPbi/aft5UT4FabmmaZ0kSZO+Tnl1XcOQ
T1oyaGWZWmo2GlBeWZbwm98btFxV1T8ZUAWDAWk5TrSjnu6udvEV4WDUZH0GpOXo9u77/vnUvxAE
wbFhGM+rij44LsXTakFGI0mSgdppmgY9sW1gnufBJoMwDCHLslOkJ7QKCFqER8QD+/fb+CPAAF5k
aRqwiqn0AAAAAElFTkSuQmCC',
		'checkbox' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABJklEQVR42tRTu2qFQBAdLyooJGm0
sUyR6vZp8w92AXu/wcoPsBULK//BD7ht6hQXbiHYiDY+SHzgA7MzkGAemxRWGRjOzs6ZM8PoCuu6
wh47wE7bLSBuA9d1bxncM7/i8F+YPzFe8nGDO3h3x3Eei6J4XjmGOeRsaz5NMI7jtaZpx3mev7XO
8xwMwzgih7uDYRgELP7qdV1DHMd0Rs6vAsuygO/7UJYlTNNEGEURtG0LmPtL4IBF2DEIAkiShBBj
0zRJEDlcga7raALLsqCqKgjDkBBjRVFoAuRwBfq+JwFVVcG2bWiahhBj7I455HAFWLfXNE3PsiyD
ruvgeR6hJEmAd5hDzrZG2L4F9pnu2KYf2PGG8yM1oiiesiy7/CjwPx/TmwADAC2u4ysq7SBQAAAA
AElFTkSuQmCC',
		'checkbox_invert' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABd0lEQVR42sSTzWrCQBDHZ5Maoh4U
AjmL91xaTy29e/dVPFkERTzkKPQphFDyAj3Z9mIKyTt4M1aS+BmNnVlYMaw9eejCkM3Mzn/mtx/s
dDrBLUOBG8edmPR6Pciy7BxgjDXQ7q8lYdff/X5/mhPg7SgK7HY78fvQ7XaHxWLRuFyz2WzCwWDw
glNZACtCoVCAOI65nqZpxuFwyFUn33a7VSQEMVRVhVKpBPP5nFHy8XiU4uv1mkkCyNXAD2emLsrl
8rPneUAipmlyE7HVaiV3sN/vrzJjuzCZTKBarZ59SZIw6RiJSzALc12XVyQM2mCy5XL5E0WR3AFx
UdJ4PIZWqwWO48BsNoNmswmj0egtCIIvsRZFp5IAcSEG+L7PjUan0yE0MAzjXdf118Viwf20yRIC
caVpCu12G8Iw5F9KJh/FLMuCSqXy91UmLuIjTtu2JWY0qNfrdDo5ASYeU61We8Jqj9euLjJ/on1c
7Bffn5zAv73GXwEGACzMwSa5vyddAAAAAElFTkSuQmCC',
		'checkbox_uncheck' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAA2klEQVR42mL8//8/AyWAcXAZ0NDQ
oASkzIGYF4f6z0B8EqjuHlwEZAAMV1VVRb18+fLyfxwAJAdSg6yHBdn4X79+8YmIiOj8+fMHq/Ug
OZAaZDEUA378+MEI0vzv3z+sBjAxMYHV4DXg79+/DLhcwMLCQtAApt+/f+M0AORnkBqcBnz79g3s
ApAhWKOMkRGsBqcB379/x2sAMzMzWA1KuCBz3r9//+Xhw4fX2djYGFhZWVEwSAwkB1KDMyFJSUmp
Af3vAGTy40hIH4EBeeDZs2e3BmleIAcABBgAsa+x0lQYCrIAAAAASUVORK5CYII=',
		'download' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACXElEQVR42oxTS2sTURT+bmeSpupM
CbGhVCkKpsHGXRfp0gd1p7hyK/oPmo0r18aVO5FQUHDoost2I+IruChNjQthEFGwUSTJpMmQaeZx
J/Pw3tCEPBS8cDj3PO53vnvPuSQMQ/B1+ZmGsbVyZy0pVVshXpUbR8wuDwff3032tNh36PU6yBTB
SXm278q61F9otSjpUvq7D2AabYQBLzoGYLSamBIEuLaDE7LMXVco9Zc7HQpq2yqzn1iGAerYCHx/
wEQc5911XXR0HWI0et4wneVfNQ2Oadke8/tDB/8KwN7jcRD4F5mg23VRdXz8JFHYZu99Xh7nfGFq
fQLAc7s8WI5JM9dv3l5d7nRD7B16qEoJXFqbX5mLEezv7KmeTRVCyKDo1AiDIFD0auPh5vN3ai2M
oBLOQI9JaAoxFLeK6pHWzPOc4TMDgDPtj5DdKmehdKpavrixrS7JBIunCCqb26pVb+R5jOfw3Ikr
LLT3sSiKOCvM4WtwQanVHZQLW/d5w8K28Wg+eqSk7e+IdxrwPO/fXYj7DayigUMhrmjt2Q/clxTa
ldOhDkw2YQRAYhRvcM2NBGkhIbaGOjTY8qncOdZDXfC8W7lc7oEsyymHDYttW0wc5udl+atHmERh
WdY3RXkqMOPFCAALxLPZbCoIAnAAx3FAKR0CEKHrJiQpnioUrPjEFUzTjOhsAnd3d6GqKlyXMnHB
8HqVgWno+Qru7a33cicADMOYLpVKB5lM5lw6vdRjQak7wsB+7ePzj08HPLd/jvS/czKZvMpm/Rr+
YwmC8EbTtLd8/0eAAQCLLE31xngJDgAAAABJRU5ErkJggg==',
		'goback' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAB4ElEQVR42sRTPUgcQRR+szf75++J
YJNUSiDYTjpbwYOkMTZKQDCNl0awlVRhiyCoWAoW2iQWdiFwRYrrzRWmUK4QMRYajZfcHXu7OzO7
k7ejh24Emysc+Ibdmfe+9/O9IUop6GQZ0OGiox8qYLsuWI4Dpm0DNc0Cnk9iZvOpQdBsZhxubIAY
xkZ5bmieZm6VKkghvPZvFARA/osYxzEQQpZwY9kS0Flw7s2+esJEFEHo+xBLqbNEDCCeIyZUkqz0
umQKbS7al+irCjyKvHdvRlmjEQMPw/T4u44oBXFtSnu6TSvfZ7tPh3oH/IDz81/1sibQzmHoLbxl
rFr1wXEMeD0+wlJxrqEwbQAplQaoJP5xcPaTh9GOJuBB4C0Wx9j+/l9dSat1Uz+m3wxC8LEPnAtI
kMV1aFyrtU4ufrc+5Sjd0z3A6O8/rn2rDA93Q5IoUNSAmkng2CBwhsr86c9Dsz/PGznrqnp0eXh6
erUppVgXPIJ2CSXMApZXv3rTxZesXDfg/POXyl1tEJeIQ+z+DrWsvZx5K167iaW0cdtru96z4gxL
5UOtX6DW2akz7s8dvVVRlSTncLC+pecgldLu6gKs9eFJzM4RkgiRfk6mm6zXoW9w8EEC8uiPqWOC
fwIMAF8h6jW3+q+pAAAAAElFTkSuQmCC',
		'folder_open' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABhUlEQVR42qRTO0sDQRCe210NhBBT
pBULG7GwthCtg4LYaKe1iKAIWqQLCioRK2t7QSws/AFWthKjHCY5jUETiI+QxMvd3q2ze4lKVHKS
gW8fzOubmV1NCAHdiEamN/7QwBCusV80ZyDgBsBLzBCziP4fZqg/TiwnJUNJsrXPJQ4IakcQR14A
IQZOd9Z2OeefRsofD5VKTe2u+4WTzZXkTHx/vZVHBiCWZYNpms0A0hCUcbuzRK3WkNGJ8iYaEKRK
W8adnD24sjwKBGNomseAcwcurnNQblJur/v7Hgj0ysxj2ORV5PDAwBW09FIFSinMT074Gt3C1Hgs
UygOLu0dbksG7Kn8CtG+MORyj+DnXRCs/TylZzC5TiSDQukZIsEg2LYDnLsdwRiDS93IguNmZAns
3bSAaRTq3PZVQrVuvaVv83nsQ1EFiIbDajyyy34kZdwbmN2Qz4UJ2wlFQiGk1ePv7ePorvS7IjR4
FhngfXQxjnMd/t8PgjSuW+rY7W8k0KV8CDAA1YkbL1WFqbwAAAAASUVORK5CYII=',
		// FILES
		'file_application' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAA8ElEQVR42qRTOxKCMBTMS15sHC0o
6LXlAI49Z+AEHoHGE9h5AG5ByRE8gC2egMqhIR98LxktnQnszA4pssvu4wHzPIs1wKqqjvQ8EXeJ
2jfxgc65c13X1zzPC+essNYRjTCGz1Z470UMqQQABiJuxDAMz6a535Au7MdxLPq+D5eZZBr470ws
2rbdotbal2W5qH/XdYAUE2LMtGECAFcMBvIbKwVSSjaQPwNmCpRS0cAYI3nazBRwZdbiNE2K355q
wDNgLRuECuSWPAPWLk6AiDEBLZHmOLQPyRWCNsuyC33Cw5JFohovWPs3SrESHwEGAMdAu4cg0kx2
AAAAAElFTkSuQmCC',
		'file_code' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAB8ElEQVR42oySz0sbURDHv7vZ1B4a
coiXehc1Iv5Aj4WanL3qv+BFin+BIgg9etCqRKJGPSneRRAVQYKCe+it3kQPopu02VjJvn1x3iRv
Wd1YOjB5L2/mfd58Z8eo1+tQNj47O0bLZ/zbjqSU19L3sT8/zweWjlCgozAzs1KjoKdcSvgE1w/Y
to3C4eEkbQ3yX/qeGQB835Dqkuex156f8bdaheu6qFQqvO7Nza1QXpbSOyMAnwAKbdJvPBbDx3gc
HywLlmnCoDPHcbC4s4NMOr388+pqQt+zQhWY6rLJGDAkMCGQyWRYTiKRwIFtlyIAoSoIAVpBBEnU
uRGAL0QgIWxvIeoRlRvtgRANCaT57vYWPxYWUKXmKV9fWkL5/p57EmsAzAhA0KHC/imXUcjn8TWb
RTKZZP8yOord7W3Unp64IvEOgHtweXGB7p4eDAwOQje1r78fnV1doO4jThWKlhI8jysYHhnByfEx
iufnLEcBLotFnJ2eYmBoqCGBcltLoGB7KoVv09PIra7CeXiA8/iIfC6HyakppChmNHOjnzH0FdIk
YXNrC7I5ymsbG2rU+b+KhyUEABpb3vMsqMFqJktdJskhClegc18BfpdKbXqvB0q9bjRXhqqxJg/n
BoCq69586u39jv8wgtzo/YsAAwARX/ykmRGNRQAAAABJRU5ErkJggg==',
		'file_csv' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACQUlEQVR42oxTTWgTURD+9idWaIOI
2FDpwSJSSPSmHtpDNMWL4sWT0CAWlSpCby3RtT/EFjWCYim0NyEYKiL1Ugql4M9ZCjkUKsQWoR4U
IUWbTVr37caZaXYbkYoDLzM7M+973/feRLv/8joWl1qgadoFAC34t73xPO+T57qYGRuThMk/7W1F
fFzdfyg7PDz1i4oOL8+DW62iSostn88ju7Bwg0KNVsFHZIB3TY1b8RPHv2J85uZfRx7Ydxgdx66g
VCrhVTo9ddGy/CYBMR3XiQ9eerYr53svelgeisUiJnI5JKLRyfFcbpBKowKwqTaJqouhXFI29HTd
wdul1/j8bRnp7ufguqnrSCQSIiccDmM+n18PJHCDqippvHr2Libnh6TQFokG+T0mKVUKiu6FTbmu
5gPoFacijckzA5iYs8j3g3Od0fOS53hvKCQgzITluErtAJRVmW7cwexiFhw/nUvhcldKPOc5FzKM
AMTYBtADCT6D1oNHZbE9me3fplpjoNMmBmEzyKt6ANaoGRrOneoObt6POc91BmBjEINkqDoJ/Izv
k4874rs9Y6z1pABkRuXVkBoZges4Owxo1k5vbTTiZ6F94MP09EMaVfDsebUpZM+55kgEqysrMob1
EvSNL80oLh8RWlzk0wJPi71OtK/19sK2bfn+Q8L3QpicHfwvZFONATfzy/P3o0wGt/r6BLxi22YA
8GM9GKoGP/BPZglazd+2LAHViA3tCXrNsi2nc9NaUyz2AP9hBLLmx78FGADfLB9jTRdpQAAAAABJ
RU5ErkJggg==',
		'file_excel' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACJ0lEQVR42oxTzUtUURT/vY+pQIeI
sDJc5CKEmdqVC1tMjbQp2rQKHCKJoP4ARZpUmJJqUySC7oKhhxFhGxFE6GMdwiyEgokIjCiCkXI+
tHffm87v+t5VKaMD550z5+N3z+/eM9bdZ9ewuNQOy7IuAGjHv+VlGIYfwiDAzNiYDrj8dHVW8P7j
vsPF0dGpX5L0qWGIoNlEU5RSKpVQXFi4Lq4lWo4RCfC6tWU9c+L4V4zP3PjjyP17j6Dn2BVUq1U8
LxSmLubzcZEGcf3AzwxferzjzLef9pMeKpUKJjwP2VRqctzzhiV1RwOsqTUZNcCIl9MN/b038Wrp
BT59e4dC3xMw79o2stmsppNMJjFfKq0YCixQTaULr569hcn5EZ3oPJgy8V2uMFUKSu6FooLAigHs
ht/Qhbkzg5iYy4sdAGOnUud1nP6eREKDcBLSCZTaBKiruty4j9nFIug/mhvC5d4hbRlnLOE4BsTZ
ALANhXiCjrajWikPZwc2Ro0msKWJIBRHrNoKQI6WY+Fcd5+5+dhnnHkCUAjiCA21hQKf8U3uQU9m
p2dMd5w0AHoCUvD9zQlk106vr7bgZ7lr8O309H1ZVXD3wmgLacPo9hGt4TYKq58PoPrlEMP6HJ7G
Jm35W1+1fCMQxrdR+F5OiqmZ/wWfyY4m+BsIOxu1mmsAfqyYpdptxiSIKClYkdWg3ANR6TG1br2m
T2fRcms6fQ//IQKyHPu/BRgACLgVJgtiXMsAAAAASUVORK5CYII=',
		'file_film' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACAUlEQVR42oxTQWsTURD+9mWjezBQ
vIgWggS8NP0L9pCePYSATSFIj/4ED0JLoaSX0ItgLxIayCWIZ6XgX8geeghYQjAHLyFBsiHJ7r4X
ZybddcOq+ODtvP1m5tuZ781aq9UKvF6enLwg8xj/Xl+NMbdGa3w6OxPAjjzkeNI6Pr70yRnwNgaa
yKMPuK6L1vX1azpatL9FeSom0NoynBQEsv3FAvPZDJ7nYTqdiv14enpJcfsU/ixFoImAqRU9s5kM
nGwW92wbtlKwCBuPx3jXbqO0s/P+pts9iPLsRAWKk5XQQEjiFYYolUrSTi6XwxfXnaQIQq6AkttX
V/g+GKDf72+oVygU8Gh7G5WjI4lNtxCG8u0BJZbLZW4J+4dvZXP/jP0YDqUdjk1VQKC0wImdToeq
DknEJWmuY4xXZk2gUhVQgrLWFpVKBQHdxErZyDlKzoyxj7UJEwR2gkA04KA2qc22+/kCvV4PjuMI
JuLSrYSJFn5rEARxBdVqFb7v4+neGzx/9UEqYIx90gLF/qkCxRVwcLPZFKt9mgFtYgx3Yxj+RQO5
heVyiVqthvl8TsEOth5uYUFTyRj71LrN9C3Q2Mo5n8+j0WhgNBqhdb4XzwFjxd1dqSCK3SD4OZnc
Z1uv1+WdfyGevKQ1ZC0SMYrdIJh53vBBsXiO/1hEMozOvwQYALlfKzInpXveAAAAAElFTkSuQmCC',
		'file_flash' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACIElEQVR42oxTQWsTQRT+djMxoSSe
tFQvXQ8eai7+gV7Ws+eC0LvHQsGbLbQWIlQPnnIOhPYgIh4KUgkW7Kk97KUXFQwUqhhpU5Nts9nZ
Xd+bvB0jiDjw5T3eznzv+2ZenEMA9ZUVOI5zn9Ib+Pdqp2n6OU0SvNrYMAXFPw+aTbQWF282V1cb
I/oYM9IUSZYhI/AKggDN3d2HlDqETzmj6wLP7nQ6WX19vfFVKXwvldCdmkK3UsG3ahU/NjfR7/cx
GAzwcm2tQd3v0bnbOYFzBGRXKSkxGyEmDAmXhJBwQbh2coJ2u219vGi1Hn/c2XliLGj6SQjXSTKv
ru/Do81HpI0r/F1R7lOd7VRJ1dsgOMvJDMGIs9EInXJ5TEZ5LHWOV8gatIaWJjpJHHsHkUjGcAiv
14M7P29yrkVCUi4WDQkroddCorUlUEO5Vj50ODNjZN+iPJR7YJJioWD9F8YErlXAm35yFkUmMk73
9nB3e9t05wYuHWISVsJRTxCoSLq8mZ01BX719wsLRkkmFpgAoqRANvSEBZc2POenOifw1Z5K7Am8
pSVDkMNYiOPfCqjT8pfp6eXXvv/oYGvrKY2q6ZzKFHJM5fYh9/WHhQ9zc9iv1bhqhHIXPmSiDJeZ
VyHh+qQF9c7zaORC+7/gZ3JFwd9I+ORlGCpLcH5mh6pkZYpftuBINKQ8BwQ6Y/eqi3F33nRcqdXq
+I9FJMd5/kuAAQCmZ/vB4znsyQAAAABJRU5ErkJggg==',
		'file_font' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABzElEQVR42oySv0vDQBTHX9JUizYW
XPyBg0sH20kc3BxiN3XVf8H/oqVQcBNBsKCLhW7VwU0Kgi7tos1QUKhbJxHSliZYksvVd9fcEWkU
H7zcy927z73vu1PG4zEwOywUDnBYgb/tgVL6Tn0fbkslPqGJFVxYreTzZRcXPeaUgo9wcYBpmlCp
148xVNA7Yp8qAb6vULbJ87i7oxF8OQ7Ytg3D4ZCPtWKxjHm7mJ6eAvgIYGgVv/FYDBLxOMxoGmiq
CgrOWZYF59UqGJnMRbvVOhL7tFAFKtuscgxwiDRCwDAMLkfXdbg3zd4UgLAKQoAoCEGJIncK4BMi
JciqrD7E8NRESpcQdgjLjQJMJKBmCE6yCqd8XDwrSGhsAlCnmkhwUlTAIE7jGZT1NaD6PIyaL7Kx
bCS/AHgPRB/6jw1Y2M9Bcs+AwVNT3k4c4SRSgufJCgbtN7Babe7CUq8dmNtITyRgbrQEVgHGo49P
cF0Xtm6uYLN2yWMX59RgPSxB+yEhqGA5twNL6BRvgGVu312zpz75x/VICfhsecx7wK4wSKaizOB2
lFDuD8Cg15sVsWgke3lKMHIoe9bo4VwJcGy7m8xmT+AfhpCuiL8FGADed++l6dMoxwAAAABJRU5E
rkJggg==',
		'file_html' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACN0lEQVR42oySz2sTURDHv/t213RJ
sprUFluqtrUhJSkKRvQezyIo6Ek86KEn/4WWQkE8eFGwihcLpRfxrgUPVgS9JGDEYhq0FK2adtea
3XTN/nL2wS4rieKDt/N4M/PZ+c4bwfd9BOvS7Ow5MkP493rued6657p4Mj/PL6TQQ47hxZmZhQ45
7WB7HlyChz+oVqtYXFmZpqNAux7msQjguoIXJNk23x3Lwp5pwjAMtFotbh/PzS1Q3FkKz3UBXAIE
aEZfWRTRJ8vYJ0mQGINAd5qm4e7SEsqFwr1apXI5zJNiFbAgmXEMOCRajoNyuczlpNNpPK1W9S6A
E1QQA/SCOCQxjO0CuI4TSfigu3hUs9D4QU2ku3EVuJATMZIElxPE9gJwCfXtX7i92sSpXAYT/Qyy
zCCJDA9ff8P1UhKTKQ5gXU106DLAPni2hhOjKjXNxICawMbGDvozSZQmMlh+8YnLcmIAKQbgPXj7
roHPOIitZhMv6xrSioI3yxXki3m8X/sImZV4bLcE2+YVeNYetk0GrcOws9uB/72No6OTWH1Vw37y
iUEPKLa3BHIWjw3D0r5AlPqgJFQoShrmTx1JGciPDfExdP7SA/4KNy6egeq3MZBIYCybxfjgIKS2
iRRMTJ8v8VeKS4gANLZcztSRDO5cO41C1oK2tQ7tawOFQz5uXTmJ4uEDvIIw9o8e7Op6IjxPjai4
f/U4n7xgDkLrkRVotOOxEcA0jM1UsXgT/7EIshmefwswADu5CJSIjC4XAAAAAElFTkSuQmCC',
		'file_illustrator' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACN0lEQVR42oxTO2hUQRQ972cWDGwS
RDQgJIWIbGEnFja7KVK5jaS0sVBLMaCVBoKBiETEGFhNUrgQUiimi0Jga0GQ1c5f44IEQaLsvrfv
Mx/vnbc72YCIA3fPzLy5554zc9f5ugjciu7AcZwLAI7j36OhlPqipMTLhQWz4fPP1UIdte6l8frc
XC2ljxmHUpBaQ1PwaDabqO/sXKOpQ/G5z+h6nrt0prirn4zfr8VrwxDrRaj1UejVMajaKKI3S2i3
2+h0OngxP1+j6lOUd7JP4OwuD+lisYAgcOC6DoTQSFOFJJGIY0mJGcTlFhqNhvXxaGPj9qft7btG
gZQaSpHUExfhzPwECHk9dqVNCJAb+K6LSqWCcrmMarWKU6XSnrXAB7JMwz+7gvDZCIJzK0bFr61p
gxyHfN+Q0EWbJCGlYwlyycSSxjg8880gWxiZfm5QCKAQBAdIpBD7BEmi0e3mBN/Xjhnsr7NMGXWB
51kSLydw+wR+kqh8liYIQ2kwinJkBWmq4VISk/DwCMUgAVcAFD48njQb75cnzdu/ezhhMMtgCHgw
iUc2xIAFP471A4ob3C9864yDceT8dUtgFLCFLNtXQK8w+7F7dPZpVLn5dnPzHrUqjKZeFzLynm0c
foVBC686p/E6LPG2qcPVOMkgr81Vu7m83vcDFrZ+TBCE9n/Bz+T2FPyNhDO7Yehbgt97tqmGrEwm
oWALTg8NKfcBBeXYs34Umup8qDVcKi3iPwaRtPrzPwIMANBrRRrPmJqrAAAAAElFTkSuQmCC',
		'file_image' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACEklEQVR42oxSzWoTURT+5s5fUhqs
xJHEn4ULQdJHqG4iuHMl6Cu46AO4awkUfADB4rIacCEuBSntI7SzEKW2qYtqoqIzhplJMzN3bjz3
hqkZJooXzpw75+e75zvnaJPJBPLcX1+/S6qJf59dIcSRyDK83thQBiP3kOPS1traZkLOVIoQyAg8
f8B1XWxtbz+kq0ZymOexM4As04RMSlMlyXiM0yhCGIYIgkDpV53OJsXdpvDrJYCMACQ0o6+p66iY
JizDgMEYNLJ5nocn3S7ardbTd/v7D/I8Y6YCJpOZgoECOTuco91uKzq1Wg1vXdcvAXBZASUfv7k6
p3cSdNoL3PmkYksAGefqbcuyiqnSKokKqArkv4ydB6Ao/Ph5Ds1GDMamwbqlQ7dNZDE1N8mgTwFY
qYmcjBK2+9LB+w/nYdu2ksV6HRdvtFBzHNiViuoN/wuA6sG3/i/s7JpIEgu6YaNab8JeuoKFegMm
0TNpKnyGwp8xpqmqYNDv4/DAQ6dj4/mLBfSOHbJegz+8jNFpdUqBYstTkBTIuboaEfcRDOMrdF1D
MOjB/7yDLx9jDPsRnBUUKBgFCqQbDaswPT5KMDgKMB5ymoJQe8LnTYHWVt339sYzIwSWLlDJ1Ri9
AwHv+wQ3Z2ILAEPft6V+9CxW/3Jt5NxzfeserYLcA2piHlsAiMLwZHF5+TH+4xDISX7/LcAAAoT+
AnPeeiUAAAAASUVORK5CYII=',
		'file_music' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAB3UlEQVR42oyTQUtbQRDH/2/z0khp
erB6iBVaCl7MoV/Ai8lRBE/6BTyI4LUHLxFB6KmnQr02NIVA6bmt4K3QW56YW4IgD0SCJIS80JDs
29eZNbtsebF0YDLL7MwvM7PzvCRJwLJ9dLRJpoB/y7lSqq3iGF9PTrTDNzd0sVStVE7HdDlhVQox
wc0fBEGA6tnZHh090pbJExYQx57ipMlE63g0wu/hEFEUYTAYaPvl+PiU4soUvpICxARgtKDfbCaD
uWwWj3wfvhDwyNftdvG+VkNpdfVDs9HYMXm+U4HgZKEx0BArUqJUKul28vk8vgdBL1WB5AqmAKMM
edN4hn6cs5WY2HQLUtoWXGX5ePUUichaCMfOAty3QEEuIAwlOtc3+HT5WM8kcw8Q6RbIaStwIO22
xLuNeXz+CT1Ybks6AN8B6BnoOWi04OVAYSmHsRrh5XKOkmNkyC+dFiyA3t5WoMg2OwkuOsDigkSz
K3C4PtZ3ugWKnVWB0BXQU3Hgfr2FwqvnuL0OUd5agSK/Sjy9hvKBGdhXYPtC3OHXtx/Yfe2nBjuz
BVpbfTYzqB+sgb8CZb6H6Uw8J/YvQL/Xy5mzWShO9KaWoYr3gNSNtYBhFIVPisW3+A8hSGjOfwQY
AAmA5X9qeW/EAAAAAElFTkSuQmCC',
		'file_outlook' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACL0lEQVR42oyTQWsTURDH/293Y6Qx
kWJE6iEJ6sUUvFQoFmRtjoWCWA9+AEE9eKyXUgqthYgXEaHFkxRCLyIipWALxYCWQilsT0r10vZg
DjbFkt0E972NMy+bbYNWfDCZfTM7v5nJzIqlpdt4+TEPIcQwgB78+6wEQfAtUApvpqe1weKf4Svb
eLeZOT83MTH7i5w+SxBANZtokvBxHAdzy8v36VGQfG0TGfChJ+Xa965/xqeVO3+kjCcu4UzuIWq1
Gl5PTs7eGht7ELo0xPJ93x4aentszYuLN5EWAtVqFS9KJRTy+ZnnpdI4uR5rQKPRQBAoHBzsYGPj
KeltpFJZ9PWNks6A/ZZhoFAo6HaSySTeO85+O4HRAkisrk6hqyuLgYGi1nxnO/tPWJaG0B+tg6RS
IgLU63VIKVGpfEEikcXCwl2t+c529p+MxTogSspDgOd5UMpHPH6Oyq9gcPCZ1nxnO/tjphlBzBbA
iKbAGZSS6O8fRbk8hbW1GXR3X4Btj2s7+w0KYggfk7Q8CuAeDUMgnb6IkZFXHRNgu/aHvTPEpDbk
kRZ4jOVi8bJ93BgzuWsRQFfALfj+YQU0mht73mmsf7/6aH1+/gmtKnj3gnALWbOtfRjV0cLWjyy2
9nJs1nk4GwdpzfdWL0RsQdje0cLmzllSbvRd8JiMsIK/QTiy7rpWBPi5Hy1VPCqTISTcggi1hvIe
kFBM9K7luTo7v7R7qre3iP84BNltP/8WYACCDxGMAMRLCgAAAABJRU5ErkJggg==',
		'file_pdf' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACL0lEQVR42oxTz0sbQRT+9kfc1ial
ByttKRiEgmn+geJJt1578Frw5KVHQcmtClohBRukp5wDQQ+laA9CteTUnlpkL7m0hYYKVk3RShJj
sruzfW+cjHsoxYFv3/Ay73vfN/NifAGQn5+HYRhPaHsX/18VIcR3EYZ4u7wsEzZ/npZKKE9N3Sst
LBS79KPPEAJhFCEi8PI8D6WdnWe0NQjfeoymCbx6WKtF+aWl4i/bxpHjoN7fj3oyiYNUCr9XVtBo
NNBsNvFmcbFI3R9T3YMegVEFopu0cZiN4BPOCW1Ci3BGGNjfR6VS0T5el8vPv25tvZAWAvqEBI5m
IoE7nQ5+Dg9D1GoQKm+TTtd1pZ0UqXrveSfaAh/oqq7hyAj8ahV9ExNSSVcp6iNrTEIXLYuCMDQ0
QScm2RodRb1QwK3paThjY+gokmukLE4SBsElwbnyen1yErdzOdwYHwfoBTLr67g/NydJEpalSawL
ArNHYLeVzEerqzjY3MTR9jaOd3fRODyUz8kNTCpiEl4WxSBOwB0Y74aGZCJSlyrUvqsIoJRYZCOI
W6ADBX6qUwJf7bGKfxTSMzOSoAdpwfcvFVCn2R+Dg7Mbrpv7vLb2kkZVdhZqCjlyTg8Ov0LcwsdM
Bp+yWc5KodyFi2RUwyXnVZFwPm7B/pBO0zO09P+Cn8lUCv5FwpXtVsvWBKcneqgcLVP5ZQuGipKU
54BANfqsfXbRnQ/tJbPZPK6wiGSvt/8rwADWs/nO3kG3ngAAAABJRU5ErkJggg==',
		'file_photoshop' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACUklEQVR42oyTTWgTURDH//uyiaZp
PhCtqFi11B5SCLQIIugl4LGCYpEcPHiz0lMKFuwhUihURE+CAe2hhRIU8eTFRHoQkULBXcGD0CLU
HHKxKQ356n7FmZfNJoUiPpj3dmd2fu8/780qQ7fzGI9/gaIoEwBO4d9jzXGcLce28X5hQTpUnja3
Exge/H56JZPJGhQ02RwHdquFFhkPXdexUijcp0eFUzpEVQjxrCXOpH+VBnFtSoNNyWymaUq7d2MA
N5PHUa1W8W5+Pntrbm7KzZUQ4VfVdCgUQjQaRSwWQyQSQTAYhN/vl7tn32xzeSiXy3ixuopkPP7y
h6bd8RTYliV3XHs6Ih2Pl4v48LUGqlUax0kmksmkBIbDYXzU9d0OQJjNJgzDIAMuP9DwKHUWiQt9
+PbqKsaG+2Ht7yOgqhLCSnhYtq14CgwCNKUBhcUxzGZ/4vr4MaQy6/islWBR4CiV0860JIRUdQHN
eh2iVpOAK9MFKfvtp01orycIksf6xg78Pp93j742QHiABiVbgQBIKSqVigToS5PI5bdIQRGCAoKS
OhAfrVYvwGg0YBBgJLUsHXxQQ5NLEiR7gGQLt3aG+OgsrN4SnErlecs00/YhbcdfTd9NeACvBNPs
KqBumQn37c1cvFR6uJHLPZE7U8Bxu5BX9vVCD5QwcO4PTp7faR8w3yvNnCRXfpeXTbMLYf+BEiIn
fqNR6/4XfE3CVXAYhDPp4FUPsLfrNdURTyZDyLgExV0llJuJjHK8b9V6rdY5/WL/6Ogi/mMQpNh5
/ivAAFenLIgsilWiAAAAAElFTkSuQmCC',
		'file_php' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACP0lEQVR42oxTz2sTURD+dvM22ZCE
JtFWu1TIpVUT8SJehF7iQUS8KQVPOXjwn7AtpQXBm6eigljIoSDixaIWgmAPnjQHEeqPQymVJmrT
Nluz6f6IMy+7m+RgcWCYtzPzfTvf7FvlxvQ02BRFuU5hFEdbxfO8b57r4vnCgkyITqcjDxSNpZmZ
xUMq2uyeB5dqQb1arWJpdfUOv4v8a8CoRvcy0JvDIFbFY5BtSz+0LLQODmCaJprNpozP5uYWqe8y
4cYDAqVUegpNi02KBB4OD42caVsuojEV2eNRXLh0EifGUnCIuFKphDoelMt3v6yszEsJ5Ncymcxy
Op1J0B4Q17tNLbODNy82MHnFQG48i2KxKOWkUim8rlYbAZkgm83nxxLxuMDOjoVsVg9jq5XA+7cb
mDg7AjiOnITNIbnhDlQV+VhMwdTUaaTT2kDk/M/aPnRNQ1QICGrmKV3H6RE0Gr8+m2Yb5fIn1Ovm
QOR8IgVokUhIEukSqKGEen1rdm3tw7Jh5OQOajVTFra3myRlCzdvnYdKeSZhi1B0+gno071cX/94
9cfv74+NY6cmLMuBrguMGkmUbl9E4VwOnn8XmCRCMpw+CcIdMtFx7Xf76d1Hr57M3+dmz79A8uwv
LjApwbZ7E7STu92Tw5MqUAjEIzNM7a4Z6CPhVw9IoIewxkUJPoKE8wMS+LoGZ/+nkqB/kTCSMCIE
7TXCSxXrjaJIMO9B8aMk5XtATpiwV/zxJ6CmzWShcA//YUSyGZz/CjAAZe0bjrWqAs8AAAAASUVO
RK5CYII=',
		'file_playlist' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACDklEQVR42pxTTWsTURQ982aSkqaC
2EaZSdTWWBelXVXoQhdit4oLQTf+AD8Wbty4EEKl0B9QsDuh0E0trgsFF/6BpCBuDMYkExuqmShN
cJJ5H773OjMIk4p44XIf95533rl35hpCCCi7VyrdlsHG3+0d57zKGcPb1VWdsKKKLDibpdIGpRRU
kg4liHIOHj5QqVSwubf3UB4N6Z+ieyQmYMyo1fpoNHy0GgMcuhSdFoPX4tIZer0edlZWNiRuWcJn
EwSMMTJzMYOCYyHvmLAdgslzAqcmhxg77cPzPKxvbeHm3NyrD+Xy/ehe3AKj1Gi4PtRMpHIdhSB4
Wc/hkd3D9cVbEqVywG653E0QyN7JzPkMAjkDrknC/uvATnccT2Y9GIIim81q7EgF9ZYfvoxYSbNJ
MRQHeP0zhweXBxj84Bo7WkEho2ZxrCD0apVi+9kZ3FgHnl4TsNLp0QpU8otUEPUZKbGdCTTbfRTs
NLrfhjJHTyAIAqOoFEjd6nLlkGNfem6K4ruVwovlAPmzJqxUSmNHKqh9/RW+Djx+U4NTLKBdr+LK
0rQmPWirmn+iAlLMj+u+FXja7OD97kc8v7OAC046nolpmhqbIBj4vvlZKlCmCNbuXsXxXyzgtoM/
vk6gsQmCbqczdsnJxFsT7QAPCUUYYRgamyDoHx25E/Pza/gHI4S40dmI1vl/7bcAAwAG1VM+vvUe
WAAAAABJRU5ErkJggg==',
		'file_powerpoint' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACNElEQVR42oxTTWgTQRT+dnZi0uBe
ihJ/iiKK1FYtiCgeNCUHT3oR1Kt4EAWP2ktbitX6Qz2JYMFbJbYHKXroQQqiICjoYdtb0eohHmuC
kmST7syu742bMQErPng7s9+b98379r11yncO4VJwCo7jnAawFf+2V1EUfY60xtzEhAEkP65l3mMy
OLptemxsao2CIXsUQccxYnI23/cxvbBwmbYO+acWIxO83uus5h9n59GcnLegTA40txzA95O3Ua1W
8Wx8fOrM8PCVJGRIpA5VftPo4ro1r94cYHkol8t4WCyi0Nf36EGxOEqhW4ZANULEkYYqf8PPuRE4
qRRkrhddR85DdveA41IIFAoFI8fzPLz0/UrrAqEaa0SgUJkdQrD8DpljF5A5fhHVt08MzvENUhoS
roRNae1YgjBoIlYK9ZVF8F70DKDxxUfwdcngjGWoqnYSrZQlkKreQKRDiO5daK58ROnqHhNI7z5s
cI6nXNd+E/c3gWirgCRoBe/cDbjbD1LrpFn5nXGOC0piEq6EV9VGIFmjKxxkczuRvf60owOMc1wk
2jnZJRmqXQK18c3S2Vx+vTZ27T9hCayEMPxTAfVmcNndjPve4NCHmZl7NKrg2YuSKeSVsZYxVYeE
56levEjvY9Tcw7dxkln53XwpeiYkjHdImFU7KLlm/wtuk0gq+BsJZwa1mrQEPyp2qNK2TCYhZwlO
shpSngNyyrFnZb1mbudDpY39/XfxH0Ykpdb+lwADAIPMEA5m8eneAAAAAElFTkSuQmCC',
		'file_swf' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACLElEQVR42oxTz0tUURT+3o9xxGai
QCRdDUgLZ6A/4rUT+gcCt5oLQRhol6ImTGAtWshbuRgY3ES0EkIZXBRBtXgQEVSgIFhkk9rMOI7v
V9+5c99zFhFd+O453DnnO+c774zxHkBlYQGGYdyhO4p/n3oURV+jMMTz1VX1YMt1t1pFbWpqrLq4
6F7wR18QRQjjGDEhx/M8VLe379E1iC8Jo2kCj4v7+3FlZcX9Ztv4kc3iaGgIR7kcvufz+Lm2hmaz
iVarhWfLyy6r32bezYTA+AjEV+lkhY3wiXOiQ7SJM2L48BD1ej3V8bRWe/B5a+uhkhDwColA9zY4
PY0brqsCPzkOfu/uwmafDn2Rk2dXLz3vOJUgiRe6quDKzAxONjbgManBZOlogNKEhINWSUEYGgmB
3eWVISypXiwiMz6Oa8SY7+PD7KwiH8xketFBoEjCILgkONetD3Boo3NzCBsNvCFBV89BFbCsVL/V
IzBTgo4e3K2lJVyfnMTe+jpa+i0ZqMmkhMSiDfoJurrK23IZIGI91IiI9XxMrV1ILM4i6JNgMuCJ
fKpTQkb7S9sTjcL8vCJIoCT4/mUHrFTeGxkpv3Cc++82Nx9xVVXlSG+hWHlLF0fNsk/Cq4kJvC6V
5FU1KlUkSVm9XGpfNYm890uwdwoFrlw7/V/IZzJ1B38jkcxOu22nBKfH6VJl0za1XpFgaKtIZZkI
5qSx9lmvugQd5EqlCv7jkOQg8f8IMAB0bvG1wzAhRAAAAABJRU5ErkJggg==',
		'file_terminal' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABqklEQVR42qRTO27CQBCd/XqdmCAh
QZ+0PkAUDoJEzxFoUkCbioiGhlvACWhzgIiOlFQIBQtkMDaZGWOjKFKkhJWeZi3vm/f2jS1OpxNc
s3Sr1XrA+oio/JEbId50mqZP3W73udFohGl6hOMxRSSQJLQ/QpZlkJtUIIRmaG1htVq9j8evLxoP
3G2323CxWPBhAjZl/LZHhJPJ5FYbY7Jerwf1ep19USa56qncE5IkgTg+gJTkQLNLzgBtCiL3+32Y
zWawXC7ZOhEKbDYRYgfVaoXtE3a7DTeQeFiS0nA4hGazCZgFKsUl1utPiKIYjHHgeTcM53x8tpcG
ZLHT6cB0OoX5fA7tdhvCMETiFvb7FEn+N1hLDUx+BbQolVIwGo0gCAK2PxgMMG2BgWlWzG0bVqWq
lObKDQ6Hg/I8D3zfZ8t0Z2qYZQqV3JmUEyk8KamBxKrKBpJeELkIj+bu3F0ZWKFKIGe0isoOnHN4
1z03oDlbG5ytX4iF4o9PGT8iY60t554vgwGuSqKU4qwoSmIcRzjWaiBqtVoHp3D/nx9JSvkhrv0b
JVy5vgQYAEy/AyIa2kyuAAAAAElFTkSuQmCC',
		'file_text' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAABwUlEQVR42oxSzWrCQBCexKTpoaKe
pD+UohaK4sk3sL322r5C30IRhD5AoZ4Fb6X3IvTUs+bQW0sVPKsUoxGzWTszZkNKQmlgMrO733zz
zexq2+0W6LtpNq/RHcLf36uU8lP6Pjy327xhqBM8OOo2Gp0NHnpkUoKP5KqAbdvQ7ffvMNTQPlSe
HhL4viYpyfPYNus1uMslOI4Di8WC/VOr1UHcJcLPYwQ+EhC1jn8zlYJ904Q9wwBD10HDvdlsBg+9
HtTL5cf34fBW5RkRBTolvw0GLDubzcJqtYKLUglACKjX67yfTqfhxbbnMQJBCpCghAnUCoFzuRwr
2QEECNxX2BiBLwS3MBqNuPp0OmWSr2CQmUwGXNeFarXK2CQCbqFYLIbVlRJltE4hhrDxFnCTaMfj
cZiolJCnNc3kOJ9nbBIBz0ApiM4hGpt4KyKxBc/TozOga6PEaHXLsuAMFRA2uQVUUCgUeE3VKPEE
h0dEklShaQE2uYVAgZJLVU9RAaBsCK6QBp3YAj5bjq9qtd0MaPL0wNBzuYBEi2B/EXzP55aKqRWq
RERa4IlE0rNGi2JDgqXjTA4qlXv4x4ckExX/CDAAS8k1lGXeb88AAAAASUVORK5CYII=',
		'file_word' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAACKUlEQVR42oxTTWjUUBD+3kvSFm0Q
RaxWkIoo/QGtB6EgdGURBKUg0pMXPepNL0UsUigWqhdFRAsehMJaBBEvHsqCtgpSEGoUBKH1VPSk
uwib7rp5L3HmbfJ2RSsOzPuS+fnezGQiRseKQMdrCCFGAOzCv+VFHMersdZ4OjVlDC4fIjqI2H3f
PTsxMVMnZ8Qax9BJgoSUJQgCzBaLFzicdCVjdJFgIdFbckIP49zVl39c2bN7E86P9qBSqeDJ5OTM
mfHxi6nLkLhRXeee3T6xYc2nL81zeyiVSrhbKCDf33//TqFwjVzXDUGtqhB8+o4rt5Zw9tR+PHq+
YnH68hDY70qJfD5v2vF9H/NBUM4ukLVQoW/vVtSrGiPDe5CoxCLb2d/muoaEK2FRWgs7g2oYQVGw
jIF3H79hX7eP5RTZzv4Oz2tEK2VItFJNgvWKQhTF8KTAwpsvONy7HYuEg4RsZ7/nOHYmToNA2hay
Cjwh8WrpKw71bjM4SJhVICmJSbgSRtVC4NbWuSwHD24et7c8vnfSINvZL9PeOdmhWajWFqKferHv
6MPcRp9x6EiXJbAtRFGzAvoyx9o2l7HzwPLY27m5G7Sq4N2L0y1kZFsmwsyypQV/xyr8rs+NAfNQ
6OQkg/xuJkVnSsL231pwOz/QoFr+CwqQaQV/I+HMahi6luBH2S5Vuy2TSUi5BZGiIeVlIqWc9uYe
hI3rKWitc2BgGv8hRLKWPf8SYAD9ug5FROXK3QAAAABJRU5ErkJggg==',
		'file_zip' => 'iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAB6ElEQVR42nSTz0sCURDHZ9+u2cWD
hw51EiEPgX9AhCDmoUNdC0HwFPQ/SGmG/4AECZ1MOhl1jkQE6eyCnSzEe6CEStT+sJlpn+22NfCc
dd68z858560yn8+BbL9Y3EO3Co69DofQrtUgmcvBSiQiwy3btl9sy4LbcpkDmtzBjbWrQqH6iZsG
rrt6HfLZLAwGA9jJZEDXdbh6eDjCVAXXszwnFgDLUhAClmHwikajEA6HGTCZTGA6ncJNqVTFvG1M
X/cBLAQQWuBvQFU5RgAyBWOj0QjOr68htbFx8dTtHshzmqsCQYcFY8AD0ISAVCoFpFcoFIJ7XR/7
KjCpAgcgIY1Gg/2SpjFEceKU62/BNBct0KLet3Z3eW85EPBAKNfXAga/W8AkHAmLWK9U2EtNyNRv
gPABTAzKCpACm4kEx5vNpkdYFb35D4A1YB3w/2Onw4fzxSLYzmUjiIpw09XCjwaGsaiAIIlkEtLp
NJQRIFzicguY658CtUCHHUin3eYKCmdnHoDi5P4FWEyBfBLnThWcHh+zsG7Iny28z2aavHWU1G61
uIISfjRyOhIucz2At/E4KJ8Jso1vpwpO8nkWTkMBNbwLNAVPrvycQ/H44dy2o+Cyj35fDcZiFvwy
RYjBpNe7pOcvAQYAXEPSkFWK3c0AAAAASUVORK5CYII=',
		//'file_3' => '',
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
	);
	if (isset($strings[$lang])) {
		return $strings[$lang];
	}
	else {
		return false;
	}
}