<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset=utf-8>
    <link href="index/assets/css/style.css" rel="stylesheet" type="text/css">
    <link rel="icon" href="index/assets/image/cute.jpg">
    <title>Hello World!</title>
</head>
<h1>Hello World!</h1>
<video controls>
    <source src="index/assets/image/test.mp4" type="video/mp4">
</video>
<form action="?opa=que" method="post" enctype="multipart/form-data">
    <input type="hidden" name="ok" value="ok">
    <input type="file" name="file">
    <button type="submit">Sub</button>
    <img width="120" alt="test" src="index/assets/image/cute.jpg">
</form>

<pre>
<?php

print_r([$request, $response, $connection, 'request' => $_REQUEST, 'server' => $_SERVER]);

?>
</pre>

</html>
