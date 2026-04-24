<?php
$content = file_get_contents('food-booking.php');
$bad  = "formData.append('uid', '<?php echo (int)(\\[''user_id''] ?? 0); ?>');";
$good = "formData.append('uid', '<?php echo (int)($_SESSION[\"user_id\"] ?? 0); ?>');";
$content = str_replace($bad, $good, $content);
file_put_contents('food-booking.php', $content);
echo "Done. Count: " . substr_count($content, $good);
