<?php

private function processVideo($request, $uploadVideo)
{
    // Retrieve the uploaded video file from the request
    $file = $request->file('video');

    try {
        // Create FFMpeg and FFProbe instances for video processing and probing
        $ffmpeg = FFMpeg::create();
        $ffprobe = FFProbe::create();
    } catch (\Exception $e) {
        // If there's an error in creating FFMpeg/FFProbe instances, return an error object
        return (object)['success' => false, 'message' => $e->getMessage()];
    }

    try {
        // Store the uploaded video file in the 'videos' directory and get the storage path
        $path = $file->store('videos');

        // Get the full storage path of the uploaded video file
        $fullPath = storage_path('app/' . $path);

        // Probe the video to get its stream information
        $videoStream = $ffprobe
            ->streams($fullPath)
            ->videos()
            ->first();

        // Extract the width and height of the video
        $width = $videoStream->get('width');
        $height = $videoStream->get('height');
        $resolution = $width . 'x' . $height;

        // Get the supported resolutions for the video based on its original resolution
        $resolutions = $this->getResolutions($resolution);

        // If the resolution is unsupported, return an error object
        if (!$resolutions) {
            return (object)['success' => false, 'message' => 'Unsupported resolution'];
        }

        // Define the output directory for encoded videos
        $outputDir = storage_path('app/encode');

        // Create the output directory if it doesn't exist
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        // Loop through each supported resolution and process the video
        foreach ($resolutions as $key => $res) {
            // Extract the new width and height for the current resolution
            list($newWidth, $newHeight) = explode('x', $res);

            // Generate a unique filename for the processed video
            $uuid = uniqid();
            $rdName = "{$uuid}_{$key}p.mp4";

            // Define the output file path for the processed video
            $outputFilePath = $outputDir . '/' . $rdName;

            // Open the original video file using FFMpeg
            $video = $ffmpeg->open($fullPath);

            // Resize the video to the new dimensions
            $video->filters()->resize(new Dimension($newWidth, $newHeight));

            // Set the video format to H.264 with AAC audio codec
            $format = new X264();
            $format->setAudioCodec('aac');

            // Save the processed video to the output file path
            $video->save($format, $outputFilePath);

            // Define the final destination path for the processed video
            $destination = getFilePath('video') . '/' . $rdName;

            // Move the processed video from the output directory to the final destination
            File::move($outputFilePath, $destination);

            // Save the video file information to the database
            $videoFile = new VideoFile();
            $videoFile->video_id = $uploadVideo->id;
            $videoFile->file_name = $rdName;
            $videoFile->quality = $key;
            $videoFile->save();
        }

        // Delete the original uploaded video file after processing
        File::delete($fullPath);

        // Return a success object indicating that the video was saved successfully
        return (object)['success' => true, 'message' => 'Video saved successfully'];

    } catch (\Exception $e) {
        // If any exception occurs during processing, return an error object
        return (object)['success' => false, 'message' => $e->getMessage()];
    }
}

private function getResolutions($resolution)
    {
        $resolutions = [];
    
        switch ($resolution) {
            case '7680x4320': // 8K
                $resolutions = [
                    '4320' => '7680x4320',
                    '2160' => '3840x2160',
                    '1440' => '2560x1440',
                    '1080' => '1920x1080',
                    '720' => '1280x720',
                    '480' => '640x480'
                ];
                break;
            case '3840x2160': // 4K
                $resolutions = [
                    '3840x2160' => '3840x2160',
                    '1440' => '2560x1440',
                    '1080' => '1920x1080',
                    '720' => '1280x720',
                    '480' => '640x480'
                ];
                break;
            case '2560x1440': // 2K
                $resolutions = [
                    '1440' => '2560x1440',
                    '1080' => '1920x1080',
                    '720' => '1280x720',
                    '480' => '640x480'
                ];
                break;
            case '1920x1080': // Full HD
                $resolutions = [
                    '1080' => '1920x1080',
                    '720' => '1280x720',
                    '480' => '640x480'
                ];
                break;
            case '1280x720': // HD
                $resolutions = [
                    '720' => '1280x720',
                    '480' => '640x480'
                ];
                break;
            case '640x360': // SD
                $resolutions = [
                    '480' => '640x480'
                ];
                break;
        }
    
        return $resolutions;
    }
    