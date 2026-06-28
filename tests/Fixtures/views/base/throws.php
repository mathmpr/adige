<?php

echo 'before-error';
throw new RuntimeException('view exploded');
