<?php
// $Id: imagemanager.php 506 2006-05-26 23:10:37Z skalpa $
//  ------------------------------------------------------------------------ //
//                XOOPS - PHP Content Management System                      //
//                    Copyright (c) 2000 XOOPS.org                           //
//                       <http://www.xoops.org/>                             //
//  ------------------------------------------------------------------------ //
//  This program is free software; you can redistribute it and/or modify     //
//  it under the terms of the GNU General Public License as published by     //
//  the Free Software Foundation; either version 2 of the License, or        //
//  (at your option) any later version.                                      //
//                                                                           //
//  You may not change or alter any portion of this comment or credits       //
//  of supporting developers from this source code or any supporting         //
//  source code which is considered copyrighted (c) material of the          //
//  original comment or credit authors.                                      //
//                                                                           //
//  This program is distributed in the hope that it will be useful,          //
//  but WITHOUT ANY WARRANTY; without even the implied warranty of           //
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            //
//  GNU General Public License for more details.                             //
//                                                                           //
//  You should have received a copy of the GNU General Public License        //
//  along with this program; if not, write to the Free Software              //
//  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307 USA //
//  ------------------------------------------------------------------------ //

include "../../mainfile.php";


error_reporting(E_ALL);
$group = array(XOOPS_GROUP_ANONYMOUS);

if (is_object($xoopsUser)) {
  $group =& $xoopsUser->getGroups();
	 
// site directories 
 if (isset($_REQUEST['imgcat_id'])){ // POST or GET
   $showPulldown = 'yes';
   $numberImagesShown = 15;   
   $imgcat_handler =& xoops_gethandler('imagecategory');
   $catlist =& $imgcat_handler->getList($group, 'imgcat_read', 1);
//print_r($catlist);
   $currentcat_id = intval($_REQUEST['imgcat_id']);
   $imgcatname = @$catlist[$currentcat_id];
   $cleanimgcatname = preg_replace("/[^a-z0-9\\.\\-\\_]/i", '_' ,$imgcatname); //replace odd characters with underscore
   $userID = $xoopsUser->getVar('uid');
   $userIDcode = str_pad($userID, 5, "0", STR_PAD_LEFT); // always have 5 digits ie: userid 123 becomes 00123
   $activeDir = XOOPS_UPLOAD_PATH.'/'.$cleanimgcatname;
  } 

// or user's directory
 if ( (!isset($_GET['imgcat_id']) && !isset($_POST['imgcat_id']) ) || $imgcatname == 'User Images'){
   $showPulldown = 'no';
   //$numberImagesShown = in_array(12, $group) ? 10 : 15; // groupID 12 gets 15 images in their dir
   $numberImagesShown = 15; // groups gets 15 images in their dir
   $userID = $xoopsUser->getVar('uid');
   $userIDcode = str_pad($userID, 5, "0", STR_PAD_LEFT); // always have 5 digits ie: userid 123 becomes 00123
   $userDir = 'user_'.$userIDcode; // this is the folder in uploads dir for this user ie: user_00123
   $activeDir = XOOPS_UPLOAD_PATH.'/'.$userDir;
  }

// create directory if not existing
 if (!file_exists($activeDir)){
   mkdir($activeDir, 0700);
   // add an htaccess file to prevent script and non-image files being accessed...
   $filename = $activeDir.'/.htaccess';
   $fh = fopen($filename, 'w');
   $stringData  = "AddHandler cgi-script .php .pl .py .jsp .asp .htm .shtml .sh .cgi\n";
   $stringData .= "Options -ExecCGI\n";
   $stringData .= "Order Deny,Allow\n";
   $stringData .= "Deny from all\n";
   $stringData .= "<FilesMatch '\.(gif|jpe?g|png)$'>\n";
   $stringData .= "Allow from all\n";
   $stringData .= "</FilesMatch>\n";
   $stringData .= "Options -Indexes\n"; // prevent browsing users dir
   fwrite($fh, $stringData);
   fclose($fh);
   chmod($filename, 0400);
  }
}
//delete image associated with thumbnail
if ((isset($_POST["delete"])) && ($_POST["delete"] == "deleteimage")) {
 $fileToDelete = $_POST['filename'];
    $fileToDelete = basename($fileToDelete);
 	$fileToDelete = str_replace('/', '', $fileToDelete);
	$fileToDelete = str_replace('..', '', $fileToDelete);
   unlink(XOOPS_UPLOAD_PATH.'/'.$userDir.'/'.$fileToDelete);   
}

//thumb creation function
function im_manager_createThumbnail($source,$thisActiveDir)
{
    $use_image_processor = 'magick'; // magick or GD
	$thumb_width = 130;
	$thumb_height = 130;
	$img_path = $thisActiveDir;
	$thumb_path = XOOPS_ROOT_PATH.'/cache';
	$src_file = $img_path.'/'.$source;
	$new_file = $thumb_path.'/thumb_'.substr($source,2); // remove v_ from filename

	if (!filesize($src_file) || !is_readable($src_file)) {
		return false;
	}

	if (!is_dir($thumb_path) || !is_writable($thumb_path)) {
		return false;
	}

	$imginfo = @getimagesize($src_file);
	if ( NULL == $imginfo ) {
		return false;
	}
		
	if($imginfo[0] > $imginfo[1]) { // check for landscape or portrait
		$imgformat = 'landscape';
	} else {
	   $imgformat = 'portrait';
	}	

    if ($imgformat == 'landscape') {
	$newWidth = (int)(min($imginfo[0],$thumb_width));
	$newHeight = (int)($imginfo[1] * $newWidth / $imginfo[0]);
	} else {
	$newHeight = (int)(min($imginfo[1],$thumb_height));
	$newWidth = (int)($imginfo[0] * $newHeight / $imginfo[1]);	
	}
	
	if ($use_image_processor == 'magick')
	{
	
		if (preg_match("#[A-Z]:|\\\\#Ai",__FILE__)){
			$cur_dir = dirname(__FILE__);
			$src_file_im = '"'.$cur_dir.'\\'.strtr($src_file, '/', '\\').'"';
			$new_file_im = '"'.$cur_dir.'\\'.strtr($new_file, '/', '\\').'"';
		} else {
			$src_file_im =   @escapeshellarg($src_file);
			$new_file_im =   @escapeshellarg($new_file);
		}
		//$path = empty($xoopsModuleConfig['path_magick'])?"":$xoopsModuleConfig['path_magick']."/";
		$path = '/usr/bin/'; // TODO set in admin
		// $magick_command = $path . 'convert -quality 85 -antialias -sample ' . $newWidth . 'x' . $newHeight . ' ' . $src_file_im . ' +profile "*" ' . str_replace('\\', '/', $new_file_im) . '';
		$magick_command = $path . 'convert -filter Lanczos -resize '. $newWidth .' -unsharp 1x3+1+.1 -quality 90  ' . $src_file_im . ' +profile "*" ' . str_replace('\\', '/', $new_file_im) . '';

		@passthru($magick_command);
		if (file_exists($new_file)){
				return true;
		}
	}

	$type = $imginfo[2];
	$supported_types = array();

	if (!extension_loaded('gd')) return false;
	if (function_exists('imagegif')) $supported_types[] = 1;
	if (function_exists('imagejpeg'))$supported_types[] = 2;
	if (function_exists('imagepng')) $supported_types[] = 3;

    $imageCreateFunction = (function_exists('imagecreatetruecolor'))? "imagecreatetruecolor" : "imagecreate";

	if (in_array($type, $supported_types) )
	{
		switch ($type)
		{
			case 1 :
				if (!function_exists('imagecreatefromgif')) return false;
				$im = imagecreatefromgif($src_file);
				$new_im = imagecreate($newWidth, $newHeight);
				imagecopyresized($new_im, $im, 0, 0, 0, 0, $newWidth, $newHeight, $imginfo[0], $imginfo[1]);
				imagegif($new_im, $new_file);
				imagedestroy($im);
				imagedestroy($new_im);
				break;
			case 2 :
				$im = imagecreatefromjpeg($src_file);
				$new_im = $imageCreateFunction($newWidth, $newHeight);
				imagecopyresized($new_im, $im, 0, 0, 0, 0, $newWidth, $newHeight, $imginfo[0], $imginfo[1]);
				imagejpeg($new_im, $new_file, 90);
				imagedestroy($im);
				imagedestroy($new_im);
				break;
			case 3 :
				$im = imagecreatefrompng($src_file);
				$new_im = $imageCreateFunction($newWidth, $newHeight);
				imagecopyresized($new_im, $im, 0, 0, 0, 0, $newWidth, $newHeight, $imginfo[0], $imginfo[1]);
				imagepng($new_im, $new_file);
				imagedestroy($im);
				imagedestroy($new_im);
				break;
		}
	}

	if (file_exists($new_file))	return true;
	return false;
}



if (!isset($_REQUEST['target'])) {
    exit();
}
$target = $_REQUEST['target'];
$op = 'list';
if (isset($_GET['op']) && $_GET['op'] == 'upload') {
    $op = 'upload';
} elseif (isset($_POST['op']) && $_POST['op'] == 'doupload') {
    $op = 'doupload';
}


if ($op == 'list') {
    require_once XOOPS_ROOT_PATH.'/class/template.php';
	$xoopsTpl = new XoopsTpl();
	$target = htmlspecialchars($_GET['target'], ENT_QUOTES);
	$xoopsTpl->assign('target', $target);
	
	// scanning of images in the directory
   $dir = $activeDir;
   // PHP 4 method follows
   $dh  = opendir($dir); 
   while (false !== ($filename = readdir($dh))) {
     $filenames[] = $filename;
     $filedates[] = filemtime($dir.'/'.$filename);
   }
   arsort($filedates); // reverse date order...
   foreach ($filedates as $key=>$value) {
     $files[] = $filenames[$key];
   }
   foreach($files as $i => $value) { // remove unwanted files from array
     if (substr($value, 0, 1) == '.') {
        unset($files[$i]);	
        }
   }
   $files = array_values($files); // reset array

   $imgcount = count($files);    
   $thumbs = array();
   $originalFileName = array();   
  
   $start = isset($_GET['start']) ? intval($_GET['start']) : 0; // first, second, third page etc
   $max = ($imgcount > 15) ? 15 : $imgcount;
   $startpoint = $start;
   $endpoint = $start+$max;
   if ($endpoint > $imgcount){$endpoint = $imgcount;}
   for ($i = $startpoint; $i < $endpoint; $i++) {
           $thumbs[$i] = 'thumb_'.substr($files[$i],2);  // array of the thumbnail image filenames
           $originalFileName[$i] = $files[$i];  // array of the original image filenames  
           //TODO check if file exists to save work....
           im_manager_createThumbnail($files[$i],$activeDir); // makes the thumbnail on the cache folder on the file system 
           $src = XOOPS_URL.'/cache/'.$thumbs[$i];
           $xoopsTpl->append('dir_images', array('id' => $originalFileName[$i], 'src' => $src));
   }
      
    $xoopsTpl->assign('dir_images_empty', XOOPS_URL.'/modules/imagemanager/images/image_manager_empty_image.jpg');
    $xoopsTpl->assign('dir_image_total', $imgcount);
    $xoopsTpl->assign('show_pulldown', $showPulldown);
	$xoopsTpl->assign('numberImagesShown', $numberImagesShown);
    $xoopsTpl->assign('lang_imgmanager', _IMGMANAGER);
    $xoopsTpl->assign('sitename', htmlspecialchars($xoopsConfig['sitename'], ENT_QUOTES));

    $imgcat_handler =& xoops_gethandler('imagecategory');
    $catlist =& $imgcat_handler->getList($group, 'imgcat_read', 1);
    $catcount = count($catlist);
    if ($catcount > 0) {
        $xoopsTpl->assign('lang_go', _GO);
		$usersImagesID = array_keys($catlist, 'User Images'); // finding the imgcat_id from the name.. not brilliant..
//print_r($usersImagesID);
        $catshow = !isset($_REQUEST['imgcat_id']) ? @$usersImagesID[0] : intval($_REQUEST['imgcat_id']); // if no catid use catid for 'User Images'
        $catshow = (!empty($catshow) && in_array($catshow, array_keys($catlist))) ? $catshow : 0;
        $xoopsTpl->assign('show_cat', $catshow);
        if ($catshow > 0) {
            $xoopsTpl->assign('lang_addimage', _ADDIMAGE);
        }
        $cat_options = '';
        foreach ($catlist as $c_id => $c_name) { // don't want 'User Images' category shown in pulldown
		  if ($c_name != 'User Images'){
              $sel = '';
              if ($c_id == $catshow) {
                  $sel = ' selected="selected"';
              }
              $cat_options .= '<option value="'.$c_id.'"'.$sel.'>'.$c_name.'</option>';
		  }
        }
        $xoopsTpl->assign('cat_options', $cat_options);
        if ($catshow > 0) {
            $image_handler = xoops_gethandler('image');
            $criteria = new CriteriaCompo(new Criteria('imgcat_id', $catshow));
            $criteria->add(new Criteria('image_display', 1));
			$total = $imgcount;
            if ($total > 0) {
                $xoopsTpl->assign('image_total', $total);
                $start = isset($_GET['start']) ? intval($_GET['start']) : 0;
                $criteria->setLimit(15);
                $criteria->setStart($start);
			    if ($total > 10) {
                    include_once XOOPS_ROOT_PATH.'/class/pagenav.php';
                    $nav = new XoopsPageNav($total, 15, $start, 'start', 'target='.$target.'&amp;imgcat_id='.$catshow);
                    $xoopsTpl->assign('pagenav', $nav->renderNav());
                }
            } else {
                $xoopsTpl->assign('image_total', 0);
            }
        }
    }
    $xoopsTpl->display('db:imagemanager_imagemanager.html');
    exit();
}

// show upload form
if ($op == 'upload') {
    $imgcat_handler =& xoops_gethandler('imagecategory');
    $imgcat_id = intval($_GET['imgcat_id']);
    $imgcat =& $imgcat_handler->get($imgcat_id);
    $error = false;
    if (!is_object($imgcat)) {
        $error = true;
    } else {
        $imgcatperm_handler =& xoops_gethandler('groupperm');
        if (is_object($xoopsUser)) {
            if (!$imgcatperm_handler->checkRight('imgcat_write', $imgcat_id, $xoopsUser->getGroups())) {
                $error = true;
            }
        } else {
            if (!$imgcatperm_handler->checkRight('imgcat_write', $imgcat_id, XOOPS_GROUP_ANONYMOUS)) {
                $error = true;
            }
        }
    }
    if ($error != false) {
        xoops_header(false);
        echo '</head><body><div style="text-align:center;"><input value="'._BACK.'" type="button" onclick="javascript:history.go(-1);" /></div>';
        xoops_footer();
        exit();
    }
    require_once XOOPS_ROOT_PATH.'/class/template.php';
    $xoopsTpl = new XoopsTpl();
    $xoopsTpl->assign('show_cat', $imgcat_id);
    $xoopsTpl->assign('lang_imgmanager', _IMGMANAGER);
    $xoopsTpl->assign('sitename', htmlspecialchars($xoopsConfig['sitename'], ENT_QUOTES));
    $xoopsTpl->assign('target', htmlspecialchars($_GET['target'], ENT_QUOTES));
    include_once XOOPS_ROOT_PATH.'/class/xoopsformloader.php';
    $form = new XoopsThemeForm('', 'image_form', 'imagemanager.php', 'post', true);
    $form->setExtra('enctype="multipart/form-data"');
    $form->addElement(new XoopsFormFile(_IMAGEFILE, 'image_file', $imgcat->getVar('imgcat_maxsize')), true);
    $form->addElement(new XoopsFormHidden('imgcat_id', $imgcat_id));
    $form->addElement(new XoopsFormHidden('op', 'doupload'));
    $form->addElement(new XoopsFormHidden('target', $target));
    $form->addElement(new XoopsFormButton('', 'img_button', _SUBMIT, 'submit'));
    $form->assign($xoopsTpl);
    $xoopsTpl->assign('imgcat_maxsize', $imgcat->getVar('imgcat_maxsize'));
	$xoopsTpl->assign('imgcat_maxwidth', $imgcat->getVar('imgcat_maxwidth'));
	$xoopsTpl->assign('imgcat_maxheight', $imgcat->getVar('imgcat_maxheight'));
    $xoopsTpl->display('db:imagemanager_imagemanager2.html');
    exit();
}

// 'do' the uploading
if ($op == 'doupload') {
    if ($GLOBALS['xoopsSecurity']->check()) {
        $image_nicename = isset($_POST['image_nicename']) ? $_POST['image_nicename'] : '';
        $xoops_upload_file = isset($_POST['xoops_upload_file']) ? $_POST['xoops_upload_file'] : array();
        $imgcat_id = isset($_POST['imgcat_id']) ? intval($_POST['imgcat_id']) : 0;
        include_once XOOPS_ROOT_PATH.'/class/uploader.php';
        $imgcat_handler =& xoops_gethandler('imagecategory');
        $imgcat =& $imgcat_handler->get($imgcat_id);
        $error = false;
        if (!is_object($imgcat)) {
            $error = true;
        } else {
            $imgcatperm_handler =& xoops_gethandler('groupperm');
            if (is_object($xoopsUser)) {
                if (!$imgcatperm_handler->checkRight('imgcat_write', $imgcat_id, $xoopsUser->getGroups())) {
                    $error = true;
                }
            } else {
                if (!$imgcatperm_handler->checkRight('imgcat_write', $imgcat_id, XOOPS_GROUP_ANONYMOUS)) {
                    $error = true;
                }
            }
        }
    }
    else {
        $error = true;
    }
    if ($error != false) {
        xoops_header(false);
        echo '</head><body><div style="text-align:center;">'.implode('<br />', $GLOBALS['xoopsSecurity']->getErrors()).'<br /><input value="'._BACK.'" type="button" onclick="javascript:history.go(-1);" /></div>';
        xoops_footer();
        exit();
    }
    $uploader = new XoopsMediaUploader($activeDir, array('image/gif', 'image/jpeg', 'image/pjpeg', 'image/x-png', 'image/png'), $imgcat->getVar('imgcat_maxsize'), $imgcat->getVar('imgcat_maxwidth'), $imgcat->getVar('imgcat_maxheight'));
	
	$uploadedFilenameWithExtension = $_FILES['image_file']['name'];
	$uploadedFilenameWithExtension = preg_replace("/[^a-z0-9\\.\\-\\_]/i", '_' ,$uploadedFilenameWithExtension); //replace odd characters with underscore
	$uploadedFilenameWithExtension = strtolower($uploadedFilenameWithExtension);
	$uploadedFileExtension = strrchr($uploadedFilenameWithExtension, '.');
    $uploadedFilename = substr($uploadedFilenameWithExtension, 0, strpos($uploadedFilenameWithExtension, '.')); // remove extension
	$uploadedFilename = substr($uploadedFilename, 0, 20); // don't want to go too mad with allowed filename length
	
	// 3 character random element for filename
	$randomElement = "";
	$pattern = "1234567890abcdefghijklmnopqrstuvwxyz";
	for($i=0;$i<3;$i++){
		$randomElement .= $pattern{rand(0,35)};
	  	}
			
	$uploadedFilename = 'v_'.$userIDcode.'_'.$randomElement.'_'.$uploadedFilename.$uploadedFileExtension;
	// TODO double check not over number of images quota
    $uploader->setTargetFileName($uploadedFilename);	
    if ($uploader->fetchMedia($xoops_upload_file[0])) {
        if (!$uploader->upload()) {
            $err = $uploader->getErrors();
        }
    } else {
        $err = sprintf(_FAILFETCHIMG, 0);
        $err .= '<br />'.implode('<br />', $uploader->getErrors(false));
    }
    if (isset($err)) {
        xoops_header(false);
        xoops_error($err);
        echo '</head><body><div style="text-align:center;"><input value="'._BACK.'" type="button" onclick="javascript:history.go(-1);" /></div>';
        xoops_footer();
        exit();
    }
    header('location: imagemanager.php?imgcat_id='.$imgcat_id.'&target='.$target);
}

?>