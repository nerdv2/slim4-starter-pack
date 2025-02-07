<?php

declare(strict_types=1);

namespace App\Helper;

use Psr\Http\Message\UploadedFileInterface;

final class UploadHelper
{
    private $directory;

    public function __construct()
    {
        // default filesystem upload path, using object storage upload will ignore this.
        $this->directory    = __DIR__ . '/../../public/uploads';
    }

    public function validateFile($source_file, $target_folder, $randomize_filename = false)
    {
        $result['status']     = false;
        $result['file_path']  = "";
        $result['file_name']  = "";
        $result['extension']  = "";

        if (isset($_FILES[$source_file]) && $_FILES[$source_file]['size'] != 0) {
            $result['status']       = true;
            $result['extension']    = strtolower(pathinfo($_FILES[$source_file]["name"], PATHINFO_EXTENSION));

            $randomize_data       = "";

            if ($randomize_filename) {
                $randomize_data = "_" . bin2hex(random_bytes(32));
            }

            $file_name = str_replace(".", '', $_FILES[$source_file]['name']);
            $file_name = preg_replace('/[^A-Za-z0-9\-]/', '', $_FILES[$source_file]['name']) . $randomize_data . '.' . $result['extension'];
            $result['file_path']   = $target_folder . '/' . str_replace(" ", "-", $file_name);
            $result['file_name']   = $file_name;
            $result['original_filename'] = $_FILES[$source_file]['name'];
            $result['size'] = $_FILES[$source_file]['size'];
            $result['content_type'] = mime_content_type($_FILES[$source_file]['tmp_name']);
        }

        return $result;
    }

    public function moveUploadedFile(UploadedFileInterface $uploadedFile, $path = "")
    {
        $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);

        $dir = $this->directory;
        $permit = 0777;

        if (!is_dir($dir)) {
            mkdir($dir);
            chmod($dir, $permit);
        }

        // see http://php.net/manual/en/function.random-bytes.php
        $basename = bin2hex(random_bytes(8));
        $filename = $path . '-' . sprintf('%s.%0.8s', $basename, $extension);

        $target_directory = $this->directory . DIRECTORY_SEPARATOR . $filename;
        if ($_SERVER['DEFAULT_UPLOAD_TARGET'] == 's3') {
            $result = $this->objectStorageUpload($uploadedFile->getStream()->getMetadata('uri'), $path . "/" . $filename);

            if ($result['status']) {
                return $result['url'];
            } else {
                return false;
            }

        } else {
            $uploadedFile->moveTo($target_directory);
            return 'uploads/' . $filename;
        }
    }

    public function uploadFileFromPath($path = "", $filename = "")
    {
        $dir = $this->directory;
        $permit = 0777;

        if (!is_dir($dir)) {
            mkdir($dir);
            chmod($dir, $permit);
        }

        $target_directory = $this->directory . DIRECTORY_SEPARATOR . $path . DIRECTORY_SEPARATOR . $filename;
        if ($_SERVER['DEFAULT_UPLOAD_TARGET'] == 's3') {
            $result = $this->objectStorageUpload($target_directory, $path . "/" . $filename);

            if ($result['status']) {
                return $result['url'];
            } else {
                return false;
            }
        } else {
            return 'uploads/' . $filename;
        }
    }

    public function objectStorageUpload($source_file, $target_path)
    {
        $result['status'] = false;
        $result['message'] = "";
        $result['url'] = "";

        $credentials = new \Aws\Credentials\Credentials($_SERVER['S3_KEY'], $_SERVER['S3_SECRET']);

        $options = [
            'region' => $_SERVER['S3_REGION'],
            'endpoint' => $_SERVER['S3_ENDPOINT'],
            'version' => 'latest',
            'credentials' => $credentials,
        ];

        try {
            $s3Client = new \Aws\S3\S3Client($options);

            $s3Result = $s3Client->putObject([
                'Bucket' => $_SERVER['S3_BUCKET'],
                'Key' => $target_path,
                'SourceFile' => $source_file,
            ]);

            if ($s3Result) {
                $result['status'] = true;
                $result['url'] = $_SERVER['S3_CDN_DOMAIN'] . "/" . $target_path;
            }

        } catch (\Aws\S3\Exception\S3Exception $e) {
            $result['message'] = $e->getMessage();
        }

        return $result;
    }
}
