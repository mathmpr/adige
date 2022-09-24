<!DOCTYPE html>
<html>
<meta charset=utf-8>
<title>Hello World!</title>
<h1>Hello World!</h1>
<form action="?opa=que" method="post">
    <input type="hidden" name="ok" value="ok">
    <button type="submit">Sub</button>
</form>

<pre>
<?php

print_r([$_REQUEST, $_SERVER['method']]);

?>
</pre>

</html>
