<div class="form-group">
    <label class="col-sm-2 control-label">
        <span data-toggle="tooltip" title="<?php echo $e->helpText; ?>"><?php echo $e->label; ?></span>
        <?php if ($e->mandatory) { ?>
            <i class="fa fa-lock safe" aria-hidden="true" data-toggle="tooltip" title="Required for the vault"></i>
        <?php } ?>
    </label>
    <div class="col-sm-6">

        <?php if ($e->multipleAllowed()) { ?>
            <div class="input-group">
                <textarea
                    <?php if($e->maxLength>0) { echo 'maxlength="' . $e->maxLength .'"'; } ?>
                    class="form-control" name="<?php echo $e->key; ?>[]"><?php echo $e->value; ?></textarea>
                <span class="input-group-btn">
                    <button class="btn btn-default duplicate-field" type="button"><i class="fa fa-plus" aria-hidden="true"></i></button>
                </span>
            </div>
        <?php } else { ?>
            <textarea
                <?php if($e->maxLength>0) { echo 'maxlength="' . $e->maxLength .'"'; } ?>
                class="form-control" name="<?php echo $e->key; ?>"><?php echo $e->value; ?></textarea>
        <?php } ?>
    </div>
    <div class="col-sm-1 control-label">
        <?php if ($e->mandatory) { ?>
            <?php if($metadataExists) { ?>
                <span class="fa-stack fa-lg">
                    <i class="fa fa-lock safe fa-stack-1x" aria-hidden="true" data-toggle="tooltip" title="Required for the vault"></i>

                    <?php // @roy: depending on $e->value holding a value change color of icon
                    ?>
                    <i class="fa fa-check fa-stack-1x"></i>
                </span>
            <?php } else { ?>
                <i class="fa fa-lock safe fa-stack-1x" aria-hidden="true" data-toggle="tooltip" title="Required for the vault"></i>
            <?php } ?>
        <?php } ?>
    </div>
</div>