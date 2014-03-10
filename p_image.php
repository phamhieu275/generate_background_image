<?php
class PImage{
	// *** Class variables
	private $srcImg;
	private $width;
	private $height;
	private $destImg;

	private $transparent = true;


	/*
	 * @param $action string - action
	 * @param $srcPath string/array - the name of source folder
	 * @param $destFilename string - the name of destination css file
	 * @param $params array - addition params
	 */
	function merge($action, $srcPath, $destFilename, $params = array()){
		$handle 	= opendir($srcPath);
		if (!$handle) {
			echo "Don't open folder.";
	        return false;
	   	}

	   	$default = array(
	   		'prefixCss'		=> 'icon-',
	   		'limitWidth'	=> 1500,
	   		'limitHeight'	=> 1500,
	   		'separate' 		=> 10,
	   		'cellWidth' 	=> 30,
	   		'cellHeight' 	=> 30,
	   		'updateFeature'	=> true,
	   	);

	   	$options 	= array_merge($default, $params);

	   	$limitWidth 	= $options['limitWidth'];
	   	$limitHeight 	= $options['limitHeight'];
	   	$separate		= $options['separate'];

		$maxWidth 	= $maxHeight = 0;
		$widthPos 	= $heightPos = 0;

		try{
			if($options['updateFeature'] && file_exists($destFilename)){
				$oldImage = $this->open($destFilename);

				// *** Get width and height
				$oldImageWidth  = imagesx($oldImage);
				$oldImageHeight	= imagesy($oldImage);

				$limitWidth += $oldImageWidth;
				$limitHeight += $oldImageHeight;

				$destImg 	= imagecreatetruecolor ($limitWidth, $limitHeight);
				$destImg = $this->copy($destImg, $oldImage, 0, 0, 0, 0, $oldImageWidth, $oldImageHeight);
				imagedestroy($oldImage);

				$maxWidth = $oldImageWidth;
				$maxHeight = $oldImageHeight;

				$cssData = array();
			}else{
				$destImg 	= imagecreatetruecolor ($limitWidth, $limitHeight);
				$cssData 	= array(
					".icon {
						*margin-right: .3em;
						background-image: url(\"$destFilename\");
						background-repeat : no-repeat;
					}"
				);
			}

			$transparent = imagecolorallocatealpha( $destImg, 0, 0, 0, 127 ); 
			imagefill($destImg, 0, 0, $transparent); 
			imagealphablending($destImg, true); 

	    	switch($action){
				case 'horizontal':
					$widthPos = $maxWidth;
					break;
				case 'vertical':
					$heightPos = $maxHeight;
					break;
				case 'grid':
					$heightPos = ceil($maxHeight / $options['cellHeight']) * $options['cellHeight'];
					$maxRowHeight = 0;
					break;
			}

		   	while (false !== ($entry = readdir($handle))) {
	            if ($entry == "." || $entry == "..") {
	            	continue;
	            }
	            $srcImg 	= $this->open($srcPath.'/'.$entry);

	            // *** Get width and height
				$width  	= imagesx($srcImg);
				$height 	= imagesy($srcImg);

				if($this->transparent){
					imagealphablending($destImg, true);
				}
				 
				switch($action){
					case 'horizontal':
						if($widthPos + $width > $limitWidth){
							throw new Exception('Exceed limited width. Please extend the width of temp image to larger than ' . $limitWidth . 'px');
						}
						
						$cssWidthPos 	= $widthPos > 0 ? 0 - $widthPos : $widthPos;
						$cssHeightPos 	= $heightPos > 0 ? 0 - $heightPos : $heightPos;

						imagecopy($destImg, $srcImg, $widthPos, $heightPos, 0, 0, $width, $height);

						$widthPos 	+= $width + $separate;

						//store width, height to reduce the size of final image.
						$maxWidth += $width + $separate;
						if($maxHeight < $height){
							$maxHeight = $height;
						}
						break;
					case 'vertical':
						if($heightPos + $height > $limitHeight){
							throw new Exception('Exceed limited height. Please extend the height of temp image to larger than ' . $limitHeight . 'px');
						}
						
						$cssWidthPos 	= $widthPos > 0 ? 0 - $widthPos : $widthPos;
						$cssHeightPos 	= $heightPos > 0 ? 0 - $heightPos : $heightPos;

						imagecopy($destImg, $srcImg, $widthPos, $heightPos, 0, 0, $width, $height);

						$heightPos += $height + $separate;

						//store width, height to reduce the size of final image.
						if($maxWidth < $width){
							$maxWidth = $width;
						}
						$maxHeight += $height + $separate;
						break;
					case 'grid':
						if($widthPos + $width > $limitWidth){
							//break line
							$widthPos = 0;
							$heightPos = ceil($maxRowHeight / $options['cellHeight']) * $options['cellHeight'];
						}

						if($heightPos > $limitHeight){
							throw new Exception('Exceed limited height. Please extend the height of temp image to larger than ' . $limitHeight . 'px');
						}

						if($heightPos + $height > $maxRowHeight){
							$maxRowHeight = $heightPos + $height;
						}

						echo $entry. ' ' .$width . ' '. $height. ' '. $widthPos.' '.$heightPos.'<br/>';

						imagecopy($destImg, $srcImg, $widthPos, $heightPos, 0, 0, $width, $height);

						if($widthPos + $width > $maxWidth){
							$maxWidth = $widthPos + $width;
						}
						if($heightPos + $height > $maxHeight){
							$maxHeight = $heightPos + $height;
						}

						$widthPos = ceil(($widthPos + $width) / $options['cellWidth']) * $options['cellWidth'];
						
						break;
				}

				if($this->transparent){
					imagealphablending($destImg, false);
				}
				

				//generate css code
				$partInfo = pathinfo($srcPath.'/'.$entry);
				$className = $options['prefixCss'] . preg_replace("/_| /", "-", $partInfo['filename']);

				$cssData[] = ".$className {
					background-position : {$cssWidthPos}px {$cssHeightPos}px;
					width : {$width}px;
					height: {$height}px;
				}";

	        }

	        closedir($handle);

	        $this->destImg = imagecreatetruecolor ($maxWidth, $maxHeight);
	        $this->destImg = $this->copy($this->destImg, $destImg, 0, 0, 0, 0, $maxWidth, $maxHeight);

	        imagedestroy($srcImg);
	        imagedestroy($destImg);

		   	$this->save($destFilename);

			echo '<img src="'.$destFilename.'" />';
			print_r($cssData);

			//generate css file
			$cssFilename = preg_replace('/\.[a-zA-Z]{3}/', '.css', $destFilename);
			if($options['updateFeature']){
				file_put_contents($cssFilename, implode("\n\n", $cssData), FILE_APPEND | LOCK_EX);
			}else{
				file_put_contents($cssFilename, implode("\n\n", $cssData), LOCK_EX);
			}
			
			
		}catch(Exception $e){
			echo $e->getMessage();
		}
	}

	function copy($destImg, $srcImg, $destX, $destY, $srcX, $srcY, $srcWith, $srcHeight){
		if($this->transparent){
			// Allocate a transparent color and fill the new image with it. 
			// Without this the image will have a black background instead of being transparent. 
			$transparent = imagecolorallocatealpha( $destImg, 0, 0, 0, 127 ); 
			imagefill($destImg, 0, 0, $transparent); 
			imagealphablending($destImg, true); 

	        imagecopy($destImg, $srcImg, $destX, $destX, $srcX, $srcY, $srcWith, $srcHeight);

	        // Save transparency
	        imagealphablending($destImg, false);
        	imagesavealpha($destImg,true);
		}else{
			imagecopy($destImg, $srcImg, $destX, $destX, $srcX, $srcY, $srcWith, $srcHeight);
		}
		return $destImg;
	}


	/*
	 * @param $action - the conversion type: resize (default)
	 * @param $src - source image path
	 * @param $destFolder  - the folder where the image is
	 * @param $newWidth - the  max width or crop width
	 * @param $newHeight - the max height or crop height
	 * @param $params
	 */

	function makeImage($action = 'resize', $src, $dest, $newWidth = false, $newHeight = false, $params = array()){
		//default param for action
		$default = array(
			'degree' => 90,
			'startX' => 0,
			'startY' => 0,
		);
		$default = array_merge($default, $params);

		if(!file_exists($src)){
			return false;
		}
		// *** Open up the file
		$this->image = $this->open($src);
		// *** Get width and height
		$this->width  = imagesx($this->image);
		$this->height = imagesy($this->image);
			
		switch ($action){
			//Maintains the aspect ration of the image and makes sure that it fits
			//within the maxW(newWidth) and maxH(newHeight) (thus some side will be smaller)
			case 'resizeWithRatio':
				$this->resizeImage($newWidth, $newHeight, 'auto');
				break;
			case 'resizeCrop':
				if(isset($params['left']) && isset($params['top']) &&
				isset($params['width']) && isset($params['height'])){
					$startX = intval(round($params['left'] * $this->width / $params['width']));
					$startY = intval(round($params['top'] * $this->height / $params['height']));
					$oldWidth = intval(round($newWidth * $this->width / $params['width']));
					$oldHeight = intval(round($newHeight * $this->height / $params['height']));
					$this->destImg = imagecreatetruecolor($newWidth, $newHeight);
					imagealphablending($this->destImg, false);
					imagesavealpha($this->destImg, true);
					imagecopyresampled($this->destImg, $this->image, 0, 0, $startX, $startY, $newWidth, $newHeight, $oldWidth, $oldHeight);
				}else{
					$this->resizeImage($newWidth, $newHeight, 'crop');
				}
				break;
			case 'crop':
				$this->crop($newWidth, $newHeight, $newWidth, $newHeight, $params);
				break;
				//Resize image with fixed size
			case 'resize' :
			default :
				if(!$newWidth){
					$newWidth = $this->width;
				}
				if(!$newHeight){
					$newHeight = $this->height;
				}
				$this->resizeImage($newWidth, $newHeight);
		}
			
		//put old image on top of new image
		switch($action){
			case 'rotate':
				if(isset($params['degree'])){
					$degree = $params['degree'];
				}else{
					$degree = 90;
				}
				$this->destImg = imagerotate($this->destImg, $default['degree'], 0);
				break;
			case 'sepia':
				$percent = '80';
				imagefilter($this->destImg,IMG_FILTER_GRAYSCALE);
				imagefilter($this->destImg,IMG_FILTER_BRIGHTNESS,-30);
				imagefilter($this->destImg,IMG_FILTER_COLORIZE, 90, 55, 30);
				break;
			case 'monochrome':
				for ($c=0;$c<256;$c++){
					$palette[$c] = imagecolorallocate($this->destImg,$c,$c,$c);
				}
				//Reads the origonal colors pixel by pixel
				for ($y=0; $y< $newHeight; $y++){
					for ($x=0; $x< $newWidth; $x++){
						$rgb = imagecolorat($this->image, $x, $y);
						$r = ($rgb >> 16) & 0xFF;
						$g = ($rgb >> 8) & 0xFF;
						$b = $rgb & 0xFF;

						//This is where we actually use yiq to modify our rbg values, and then convert them to our grayscale palette
						$gs = ($r*0.299)+($g*0.587)+($b*0.114);
						imagesetpixel($this->destImg,$x,$y,$palette[$gs]);
					}
				}
				break;
		}

		$this->save($dest);
		return true;
	}

	private function open($file){
		// *** Get extension
		$extension = strtolower(strrchr($file, '.'));
		switch($extension){
			case '.jpg':
			case '.jpeg':
				$img = @imagecreatefromjpeg($file);
				break;
			case '.gif':
				$img = @imagecreatefromgif($file);
				break;
			case '.png':
				$img = @imagecreatefrompng($file);
				break;
			default:
				$img = false;
				break;
		}
		return $img;
	}

	public function resizeImage($newWidth, $newHeight, $action='exact'){
		// *** Get optimal width and height - based on $action
		$optionArray = $this->getDimensions($newWidth, $newHeight, strtolower($action));
		$optimalWidth  = $optionArray['optimalWidth'];
		$optimalHeight = $optionArray['optimalHeight'];
		// *** Resample - create image canvas of x, y size
		$this->destImg = imagecreatetruecolor($optimalWidth, $optimalHeight);
		imagealphablending( $this->destImg, false );
		imagesavealpha( $this->destImg, true );
		imagecopyresampled($this->destImg, $this->image, 0, 0, 0,0, $optimalWidth, $optimalHeight, $this->width, $this->height);
		// *** if action is 'crop', then crop too
		if ($action == 'crop') {
			$this->crop($optimalWidth, $optimalHeight, $newWidth, $newHeight);
		}
	}

	private function getDimensions($newWidth, $newHeight, $action){
		switch ($action){
			case 'exact':
				$optimalWidth = $newWidth;
				$optimalHeight= $newHeight;
				break;
			case 'portrait':
				$optimalWidth = $this->getSizeByFixedHeight($newHeight);
				$optimalHeight= $newHeight;
				break;
			case 'landscape':
				$optimalWidth = $newWidth;
				$optimalHeight= $this->getSizeByFixedWidth($newWidth);
				break;
			case 'auto':
				$optionArray = $this->getSizeByAuto($newWidth, $newHeight);
				$optimalWidth = $optionArray['optimalWidth'];
				$optimalHeight = $optionArray['optimalHeight'];
				break;
			case 'crop':
				$optionArray = $this->getOptimalCrop($newWidth, $newHeight);
				$optimalWidth = $optionArray['optimalWidth'];
				$optimalHeight = $optionArray['optimalHeight'];
				break;
		}
		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	private function getSizeByFixedHeight($newHeight){
		$ratio = $this->width / $this->height;
		$newWidth = $newHeight * $ratio;
		return $newWidth;
	}
	
	private function getSizeByFixedWidth($newWidth){
		$ratio = $this->height / $this->width;
		$newHeight = $newWidth * $ratio;
		return $newHeight;
	}
	
	private function getSizeByAuto($newWidth, $newHeight){
		if (($newWidth && !$newHeight) || ($this->height < $this->width)){
			// *** Image to be resized is wider (landscape)
			$optimalWidth = $newWidth;
			$optimalHeight= $this->getSizeByFixedWidth($newWidth);
		}
		elseif ((!$newWidth && $newHeight) || ($this->height > $this->width)){
			// *** Image to be resized is taller (portrait)
			$optimalWidth = $this->getSizeByFixedHeight($newHeight);
			$optimalHeight= $newHeight;
		}
		else{
			// *** Image to be resizerd is a square
			if ($newHeight < $newWidth) {
				$optimalWidth = $newWidth;
				$optimalHeight= $this->getSizeByFixedWidth($newWidth);
			} else if ($newHeight > $newWidth) {
				$optimalWidth = $this->getSizeByFixedHeight($newHeight);
				$optimalHeight= $newHeight;
			} else {
				// *** Sqaure being resized to a square
				$optimalWidth = $newWidth;
				$optimalHeight= $newHeight;
			}
		}
		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	private function getOptimalCrop($newWidth, $newHeight){
		$heightRatio = $this->height / $newHeight;
		$widthRatio  = $this->width /  $newWidth;
		if ($heightRatio < $widthRatio) {
			$optimalRatio = $heightRatio;
		} else {
			$optimalRatio = $widthRatio;
		}
		$optimalHeight = $this->height / $optimalRatio;
		$optimalWidth  = $this->width  / $optimalRatio;
		return array('optimalWidth' => $optimalWidth, 'optimalHeight' => $optimalHeight);
	}

	private function crop($optimalWidth, $optimalHeight, $newWidth, $newHeight, $params = array()){
		// *** Crop from exact position of image.
		if(!empty($params) && isset($params['startX']) && isset($params['startY'])){
			$cropStartX = $params['startX'];
			$cropStartY = $params['startY'];
			$crop = $this->image;
		}else{
			// *** Default crop from center after resize
			$cropStartX = ( $optimalWidth / 2) - ( $newWidth /2 );
			$cropStartY = ( $optimalHeight/ 2) - ( $newHeight/2 );
			$crop = $this->destImg;
		}

		$this->destImg = imagecreatetruecolor($newWidth , $newHeight);
		imagealphablending( $this->destImg, false );
		imagesavealpha( $this->destImg, true );
		imagecopyresampled($this->destImg, $crop , 0, 0, $cropStartX, $cropStartY, $newWidth, $newHeight , $newWidth, $newHeight);
	}

	public function save($savePath, $imageQuality="100"){
		// *** Get extension
		$extension = strrchr($savePath, '.');
		$extension = strtolower($extension);
		switch($extension){
			case '.jpg':
			case '.jpeg':
				if (imagetypes() & IMG_JPG) {
					imagejpeg($this->destImg, $savePath, $imageQuality);
				}
				break;
			case '.gif':
				if (imagetypes() & IMG_GIF) {
					imagegif($this->destImg, $savePath);
				}
				break;
			case '.png':
				// *** Scale quality from 0-100 to 0-9
				$scaleQuality = round(($imageQuality/100) * 9);
				// *** Invert quality setting as 0 is best, not 9
				$invertScaleQuality = 9 - $scaleQuality;
				if (imagetypes() & IMG_PNG) {
					imagepng($this->destImg, $savePath, $invertScaleQuality);
				}
				break;
				// ... etc
			default:
				// *** No extension - No save.
				break;
		}
		imagedestroy($this->destImg);
	}
}
?>