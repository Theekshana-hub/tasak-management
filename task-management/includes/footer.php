    </div>
</div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php
$script_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
if (strpos($script_path, '/admin') !== false || strpos($script_path, '/user') !== false || strpos($script_path, '/coordinator') !== false) {
    $js_path = '../assets/js/script.js';
} else {
    $js_path = 'assets/js/script.js';
}
?>

<script src="<?php echo $js_path; ?>?v=2"></script>
</body>
</html>
