<?php
require_once __DIR__ . '/helpers.php';

// Saves an uploaded image and returns its web address, such as "/uploads/1730000000-a1b2c3.jpg".
// Returns null if no file was sent, which is fine because the photo is optional.
function save_upload(string $field): ?string
{
    // No file was chosen
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];

    // PHP itself reported a problem during upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_error('The photo could not be uploaded. Please try again.');
    }

    // Reject files larger than the limit set in config.php
    if ($file['size'] > MAX_UPLOAD_BYTES) {
      json_error('Each file must be ' . (MAX_UPLOAD_BYTES / 1048576) . ' MB or less');
    }

    // Check the real content of the file, not the name or the type the browser claims.
    // This is what stops a PHP script from being hidden inside a file named photo.jpg.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        json_error('Only JPG, PNG, or WEBP images are allowed');
    }

    // Choose the file extension from the real type we just checked
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    // Give every file a unique random name, so files never overwrite each other
    // and visitors can't guess other people's photo names
    $name = time() . '-' . bin2hex(random_bytes(6)) . '.' . $extensions[$mime];

    // Make sure the uploads folder exists, then move the file into it
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) {
        json_error('Could not save the photo', 500);
    }

    return UPLOAD_URL . $name;
}
// Returns true when a file was attached to the named form field
function has_upload(string $field): bool
{
    return isset($_FILES[$field]) && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE;
}