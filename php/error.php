<?php
header('Content-Type: application/json');
http_response_code(200);
print json_encode(['results' => []]);