Add-Type -AssemblyName System.Drawing
$root = 'C:\xampp\htdocs\ks\WEBODER\assets\images\homestays'

function Crop-Center($src, $dst, $targetRatio) {
    $img = [System.Drawing.Image]::FromFile($src)
    $w = $img.Width; $h = $img.Height
    $cur = $w / $h
    if ($cur -gt $targetRatio) {
        # too wide: crop width
        $nw = [int]($h * $targetRatio); $nh = $h
        $x = [int](($w - $nw) / 2); $y = 0
    } else {
        # too tall: crop height
        $nw = $w; $nh = [int]($w / $targetRatio)
        $x = 0; $y = [int](($h - $nh) / 2)
    }
    $rect = New-Object System.Drawing.Rectangle($x, $y, $nw, $nh)
    $bmp = New-Object System.Drawing.Bitmap($nw, $nh)
    $g = [System.Drawing.Graphics]::FromImage($bmp)
    $g.InterpolationMode = [System.Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
    $g.DrawImage($img, (New-Object System.Drawing.Rectangle(0, 0, $nw, $nh)), $rect, [System.Drawing.GraphicsUnit]::Pixel)
    $enc = [System.Drawing.Imaging.ImageCodecInfo]::GetImageEncoders() | Where-Object { $_.MimeType -eq 'image/jpeg' }
    $ep = New-Object System.Drawing.Imaging.EncoderParameters(1)
    $ep.Param[0] = New-Object System.Drawing.Imaging.EncoderParameter([System.Drawing.Imaging.Encoder]::Quality, [long]88)
    # Ghi ra file tạm, giải phóng handle, rồi mới thay thế file gốc
    $tmp = $dst + '.tmp.jpg'
    $bmp.Save($tmp, $enc, $ep)
    $g.Dispose(); $bmp.Dispose(); $img.Dispose()
    Move-Item -Force $tmp $dst
    Write-Host "CROPPED $src -> ${nw}x${nh}"
}

# Crop portraits to 4:3 (card ratio)
$portraits = @(
    'cau-giay-business-apartment/cover.jpg',
    'cau-giay-business-apartment/workspace.jpg',
    'dong-da-cozy-studio/cover.jpg',
    'hanoi-exterior/hanoi-apartment-exterior.jpg',
    'truc-bach-balcony-room/cover.jpg',
    'truc-bach-lake-view-studio/cover.jpg',
    'west-lake-sunset-studio/cover.jpg'
)
foreach ($p in $portraits) {
    $src = Join-Path $root $p
    Crop-Center $src $src 1.3333
}

# Wide hero from the nicest living room (workcation loft floor-to-ceiling windows)
Crop-Center (Join-Path $root 'west-lake-workcation-loft/cover.jpg') (Join-Path $root 'hero-hanoi.jpg') 2.2857

Write-Host "DONE"
