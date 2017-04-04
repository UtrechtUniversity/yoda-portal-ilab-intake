<script>
    var revisionItemsPerPage = <?php echo $items; ?>;
    var browseDlgPageItems = <?php echo $dlgPageItems; ?>;
</script>
<div class="modal" id="select-folder">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <span class="modal-title">Select folder</span>
            </div>

            <input type="hidden" id="restoration-objectid" value="">

            <ol class="breadcrumb dlg-breadcrumb">
                <li class="active">Home</li>
            </ol>

            <div class="modal-body">
                <div class="col-md-12">
                    <div class="row">
                        <div class="panel panel-default">
                            <div class="panel-body">

                                <p class="alert-panel-error alert-panel hide" style="color:red;">
                                    <i class="fa fa-exclamation-triangle"></i> Something has gone wrong
                                    <br>
                                    <span></span>
                                </p>

                                <p class="alert-panel-overwrite alert-panel hide">
                                    <i class="fa fa-question-circle"></i> This file already exists. Please chose:
                                    <br>
                                    <button class="btn btn-danger" id="btn-restore-overwrite"><i class="fa fa-file-o" aria-hidden="true"></i> Overwrite</button>
                                    <button class="btn btn-info" id="btn-restore-next-to"><i class="fa fa-files-o" aria-hidden="true"></i> Place revision next to original</button>
                                    <button class="btn button grey" data-dismiss="modal">Cancel</button>
                                </p>

                                <p class="alert-panel-path-not-exists alert-panel hide">
                                    <i class="fa fa-question-circle"></i> The folder you selected does not exist anymore. Please select another.
                                </p>


                                <table id="folder-browser" class="table yoda-table table-bordered">
                                    <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Modified date</th>
                                    </tr>
                                    </thead>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn button btn-success" id="btn-restore"><i class="fa fa-magic" aria-hidden="true"></i> Restore your file</button>
                <button class="btn button grey" data-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="panel panel-default">
            <div class="panel-heading clearfix">
                <h3 class="panel-title pull-left">
                    Revisions
                </h3>
                <div class="input-group-sm has-feedback pull-right">
                    <a class="btn btn-default" href="/research/browse">Close</a>
                </div>
            </div>
            <div class="panel-body">
                <div class="input-group" style="margin-bottom:20px;">
                    <input type="text" class="form-control" id="search-term" name="searchArgument" placeholder="Search term..." value="<?php echo htmlentities($filter); ?>">
                    <span class="input-group-btn">
                        <button class="btn btn-default btn-search" type="button"><span class="glyphicon glyphicon-search"></span></button>
                    </span>
                </div>

                <table id="file-browser" class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Number of revisions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>