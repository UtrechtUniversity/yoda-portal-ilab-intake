    <div class="row">
        <div class="col-md-12">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <h3 class="panel-title pull-left">
                        Metadata form - <?php echo str_replace(' ', '&nbsp;', htmlentities(trim($path))); ?>
                    </h3>
                    <div class="input-group-sm has-feedback pull-right">
                        <a class="btn btn-default" href="/research/browse?dir=<?php echo rawurlencode($path); ?>">Close</a>
                    </div>
                </div>
                <div class="panel-body">
                    <?php if (!$writePermission && !$metadataExists) { ?>
                        <p>
                            There is no metadata present for this folder.
                        </p>
                    <?php } else { ?>
                        <div id="form" class="metadata-form"
                             data-path="<?php echo rawurlencode($path); ?>"
                             data-csrf_token_name="<?php echo rawurlencode($tokenName); ?>"
                             data-csrf_token_hash="<?php echo rawurlencode($tokenHash); ?>">
                        </div>
	            <?php } ?>
                </div>
            </div>
        </div>
    </div>

<?php if ($messageDatamanagerAfterSaveInVault) { // trick to display data via central messaging system ?>
        <script language="javascript">
            setMessage('success', '<?php echo $messageDatamanagerAfterSaveInVault; ?>');
        </script>
<?php } ?>

<?php if (!$writePermission && !$metadataExists) { ?>
<script type="text/javascript">
    var mode = "<?php echo $mode; ?>";
</script>
<script src="/research/static/js/metadata/bundle.js" type="text/javascript"></script>
<?php } ?>