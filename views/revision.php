<script>
    var revisionItemsPerPage = <?php echo $items; ?>;
    var browseDlgPageItems = <?php echo $dlgPageItems; ?>;
    var view = 'revision';
</script>

<div class="modal fade" id="select-folder" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Select folder to restore revision</h5>
                <input type="hidden" id="restoration-objectid" value="">
                <input type="hidden" id="org_folder_select_path" value="">
                <input type="hidden" id="org_folder_select_filename" value="">
            </div>
            <div class="modal-body">
                <p class="alert-folder-select">ALERT SECTION</p>

                <!--- BREADCRUMS -->
                <div class="row d-block">
                    <nav aria-label="breadcrumb flex-column">
                        <ol class="breadcrumb dlg-breadcrumb">
                            <li class="breadcrumb-item">Home</li>
                        </ol>
                    </nav>
                </div>

                <!--- FOLDER SELECTION-->
                <div class="row d-block">
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
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="btn-restore"><i class="fa fa-magic" aria-hidden="true"></i> Restore</button>
            </div>

            <!--- CoverALL Second dialog for handling of duplicate situations -->
            <div id="coverAll" class="cover restore-exists hide">
                <div class="card" style="width:100%;">
                    <div class="card-header">
                        <h5 class="card-title">The file already exists </h5>
                    </div>
                    <div class="card-body" style="height:500px;">
                        <p class="alert-dlg-already-exists"></p>
                        <h6 class="card-title">Make a selection to overwrite or rename current file</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <form id="form-restore-overwrite">
                                    <div class="row d-block">
                                        <p>Overwrite this file</p>
                                    </div>
                                    <div class="row d-block">
                                        <p>
                                            <button class="btn btn-danger" id="btn-restore-overwrite">Overwrite</button>
                                        </p>
                                    </div>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <form class="form-inline">
                                    <div class="row d-block">
                                        <p>Enter new name filename</p>
                                    </div>
                                    <div class="row d-block">
                                            <input type="text"  class="form-control" placeholder="Enter new filename" id="newFileName">
                                        <button  class="btn btn-primary" id="btn-restore-next-to">Restore with a new filename</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <div class="row">
                            <hr>
                            <button class="btn btn-secondary pull-right"  id="btn-cancel-overwrite-dialog">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>>
        </div>
    </div>
</div>

<?php echo $searchHtml; ?>

<div class="row">
    <div class="col-md-12">
        <div class="row d-block">
            <div class="card">
                <div class="card-header clearfix">
                    <h3 class="card-title pull-left">
                        Revisions
                    </h3>
                    <div class="input-group-sm has-feedback pull-right">
                        <a class="btn btn-secondary cancel" href="/research/browse">Close</a>
                    </div>
                </div>
                <div class="card-body">
                    <p class="alert-card-main hide" style="color:green;">
                        <i class="fa fa-check"></i> Your file was successfully restored!
                    </p>

                    <table id="file-browser" class="table yoda-table dataTable no-footer" role="grid">
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
</div>
