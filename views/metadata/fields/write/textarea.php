<div class="form-group">
    <label class="col-sm-2 control-label">
        <?php if ($e->subPropertiesRole=='subPropertyStartStructure'): ?>
            <i class="glyphicon glyphicon-chevron-down subproperties-toggle" data-toggle="tooltip" title="Click to open or close view on subproperties" data-html="true"></i>&nbsp;
        <?php endif; ?>
        <span data-toggle="tooltip" title="<?php echo $e->helpText; ?>"><?php echo $e->label; ?></span>
    </label>

    <div class="col-sm-9">
        <div class="row">

            <div class="col-sm-1">
                <?php if ($e->mandatory) { ?>
                    <?php if($metadataExists) { ?>
                        <span class="fa-stack ">
                            <?php
                            // this is added as stacked icons make tooltip handling harder.
                            $toolTipLock = '';
                            $toolTipCheckmark = '';
                            if($e->value) {
                                $toolTipCheckmark = 'aria-hidden="true" data-toggle="tooltip" title="Filled out correctly for the vault"';
                            }
                            else {
                                $toolTipLock = 'aria-hidden="true" data-toggle="tooltip" title="Required for the vault"';
                            }
                            ?>

                            <i class="fa fa-lock safe fa-stack-1x" <?php echo $toolTipLock; ?> ></i>
                            <?php if($e->value) { ?>
                                <i class="fa fa-check fa-stack-1x checkmark-green-top-right" <?php echo $toolTipCheckmark; ?> ></i>
                            <?php } ?>
                        </span>
                    <?php } else { ?>
                        <i class="fa fa-lock safe-single" aria-hidden="true" data-toggle="tooltip" title="Required for the vault"></i>
                    <?php } ?>
                <?php } ?>
            </div>

            <div class="col-sm-11">
                <?php if ($e->multipleAllowed()) { ?>
                    <div class="input-group">
                        <textarea
                            <?php if($e->maxLength>0) { echo 'maxlength="' . $e->maxLength .'"'; } ?>
                                name="<?php echo $e->key; ?>[]"
                                <?php if ($e->subPropertiesRole=='subPropertyStartStructure'): ?>
                                    name="<?php echo $e->key; ?>[<?php echo $e->subPropertiesStructID; ?>]"
                                <?php else: ?>
                                    name="<?php echo $e->key; ?>[]"
                                <?php endif; ?>
                                class="form-control"><?php echo htmlentities($e->value); ?></textarea>
                        <span class="input-group-btn">
                            <?php if ($e->subPropertiesRole=='subPropertyStartStructure') { ?>
                                <button class="btn btn-default duplicate-subproperty-field" type="button"><i class="fa fa-plus" aria-hidden="true"></i></button>
                            <?php } else { ?>
                                <button class="btn btn-default duplicate-field" type="button"><i class="fa fa-plus" aria-hidden="true"></i></button>
                            <?php } ?>
                        </span>
                    </div>
                <?php } else { ?>
                    <textarea
                        <?php if($e->maxLength>0) { echo 'maxlength="' . $e->maxLength .'"'; } ?>
                        class="form-control" name="<?php echo $e->key; ?>"><?php echo htmlentities($e->value); ?></textarea>
                <?php } ?>
            </div>
        </div>
        <?php  get_instance()->load->view('metadata/fields/write/compound-duplicate-button', array('e',$e)); ?>
    </div>
</div>