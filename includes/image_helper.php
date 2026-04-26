<?php
/**
 * Image Helper Functions
 * Centralized image path management for the hotel system
 */

// Base image directory
define('IMAGE_BASE_PATH', 'assets/images/');

/**
 * Get room image path
 * @param string $roomType - Room type (standard, deluxe, suite, family, presidential)
 * @param string $filename - Image filename
 * @return string - Full image path
 */
function get_room_image($roomType, $filename) {
    $path = IMAGE_BASE_PATH . "rooms/{$roomType}/{$filename}";
    return file_exists($path) ? $path : IMAGE_BASE_PATH . 'rooms/placeholder.jpg';
}

/**
 * Get food image path
 * @param string $category - Food category (ethiopian, international, beverages)
 * @param string $filename - Image filename
 * @return string - Full image path
 */
function get_food_image($category, $filename) {
    $path = IMAGE_BASE_PATH . "food/{$category}/{$filename}";
    return file_exists($path) ? $path : IMAGE_BASE_PATH . 'food/placeholder.jpg';
}

/**
 * Get service image path
 * @param string $service - Service type (spa, laundry, amenities)
 * @param string $filename - Image filename
 * @return string - Full image path
 */
function get_service_image($service, $filename) {
    $path = IMAGE_BASE_PATH . "services/{$service}/{$filename}";
    return file_exists($path) ? $path : IMAGE_BASE_PATH . 'services/placeholder.jpg';
}

/**
 * Get hotel image path
 * @param string $type - Hotel image type (exterior, interior, facilities)
 * @param string $filename - Image filename
 * @return string - Full image path
 */
function get_hotel_image($type, $filename) {
    $path = IMAGE_BASE_PATH . "hotel/{$type}/{$filename}";
    return file_exists($path) ? $path : IMAGE_BASE_PATH . 'hotel/placeholder.jpg';
}

/**
 * Get gallery image path
 * @param string $category - Gallery category (events, dining)
 * @param string $filename - Image filename
 * @return string - Full image path
 */
function get_gallery_image($category, $filename) {
    $path = IMAGE_BASE_PATH . "gallery/{$category}/{$filename}";
    return file_exists($path) ? $path : IMAGE_BASE_PATH . 'gallery/placeholder.jpg';
}

/**
 * Get user profile image path
 * @param string $filename - Image filename
 * @return string - Full image path
 */
function get_profile_image($filename) {
    if (empty($filename)) {
        return IMAGE_BASE_PATH . 'users/default-avatar.png';
    }
    $path = IMAGE_BASE_PATH . "users/profiles/{$filename}";
    return file_exists($path) ? $path : IMAGE_BASE_PATH . 'users/default-avatar.png';
}

/**
 * Get banner image path
 * @param string $filename - Banner filename
 * @return string - Full image path
 */
function get_banner_image($filename) {
    $path = IMAGE_BASE_PATH . "banners/{$filename}";
    return file_exists($path) ? $path : IMAGE_BASE_PATH . 'banners/default-banner.jpg';
}

/**
 * Get logo image path
 * @param string $filename - Logo filename
 * @return string - Full image path
 */
function get_logo_image($filename = 'logo.png') {
    $path = IMAGE_BASE_PATH . "logos/{$filename}";
    return file_exists($path) ? $path : IMAGE_BASE_PATH . 'logos/logo.png';
}

/**
 * Get icon image path
 * @param string $filename - Icon filename
 * @return string - Full image path
 */
function get_icon_image($filename) {
    $path = IMAGE_BASE_PATH . "icons/{$filename}";
    return file_exists($path) ? $path : IMAGE_BASE_PATH . 'icons/default-icon.png';
}

/**
 * Upload image to appropriate folder
 * @param array $file - $_FILES array element
 * @param string $category - Category (rooms, food, services, etc.)
 * @param string $subcategory - Subcategory (optional)
 * @return array - ['success' => bool, 'filename' => string, 'message' => string]
 */
function upload_image($file, $category, $subcategory = '') {
    // Validate file
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, GIF, and WebP allowed.'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large. Maximum size is 5MB.'];
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    
    // Determine upload path
    $upload_dir = IMAGE_BASE_PATH . $category . '/';
    if (!empty($subcategory)) {
        $upload_dir .= $subcategory . '/';
    }
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $upload_path = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $upload_path,
            'message' => 'Image uploaded successfully'
        ];
    } else {
        return ['success' => false, 'message' => 'Failed to upload image'];
    }
}

/**
 * Delete image file
 * @param string $filepath - Full path to image
 * @return bool - Success status
 */
function delete_image($filepath) {
    if (file_exists($filepath) && is_file($filepath)) {
        return unlink($filepath);
    }
    return false;
}

/**
 * Get image URL (for use in HTML)
 * @param string $path - Relative path to image
 * @return string - Full URL
 */
function get_image_url($path) {
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $base_url .= str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    return $base_url . $path;
}

/**
 * Resize image
 * @param string $source - Source image path
 * @param string $destination - Destination path
 * @param int $width - Target width
 * @param int $height - Target height
 * @return bool - Success status
 */
function resize_image($source, $destination, $width, $height) {
    list($orig_width, $orig_height, $type) = getimagesize($source);
    
    // Create image resource based on type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    // Create new image
    $new_image = imagecreatetruecolor($width, $height);
    
    // Preserve transparency for PNG and GIF
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
        imagealphablending($new_image, false);
        imagesavealpha($new_image, true);
    }
    
    // Resize
    imagecopyresampled($new_image, $image, 0, 0, 0, 0, $width, $height, $orig_width, $orig_height);
    
    // Save based on type
    switch ($type) {
        case IMAGETYPE_JPEG:
            $result = imagejpeg($new_image, $destination, 90);
            break;
        case IMAGETYPE_PNG:
            $result = imagepng($new_image, $destination, 9);
            break;
        case IMAGETYPE_GIF:
            $result = imagegif($new_image, $destination);
            break;
    }
    
    imagedestroy($image);
    imagedestroy($new_image);
    
    return $result;
}
?>