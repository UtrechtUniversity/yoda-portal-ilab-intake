<script>
    var browsePageItems = <?php echo $items; ?>;
    var browseStartDir  = <?php echo json_encode($dir); ?>;
    var view = 'browse';
</script>

<div class="modal" tabindex="-1" role="dialog" id="showUnpreservableFiles">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <h3 class="modal-title">File formats compliance with policy</h3>
                <div class="form-group">
                    <label for="file-formats-list">Select preservable file format list:</label>
                    <select class="form-control" id="file-formats-list">
                        <option value="" disabled selected>Select a file format list</option>
                    </select>
                </div>
                <p class="help"></p><br />
                <p class="advice"></p>
                <p class="checking">Checking files <i class="fa fa-spinner fa-spin fa-fw"></i></p>
                <p class="preservable">
                    This folder does not contain files that are likely to become unusable in the future.
                </p>
                <div class="unpreservable">
                    <p>The following unpreservable file extensions were found in your dataset:</p>
                    <ul class="list-unpreservable-formats"></ul>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="folder-create">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <h5 class="modal-title">Create new folder in <span id="collection"></span></h5>
                <div class="alert alert-warning" id="alert-panel-folder-create">
                    <span></span>
                </div>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">Folder name</span>
                  </div>
                  <input type="text" class="form-control" id='path-folder-create' value="">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button class='btn btn-primary btn-confirm-folder-create' data-path="">Create new folder</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="folder-delete">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <h5 class="modal-title">Delete folder in <span id="collection"></span></h5>
                <div class="alert alert-warning" id="alert-panel-folder-delete">
                    <span></span>
                </div>
                <p>Do you want to delete folder <span id="folder-delete-name"></span>?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class='btn btn-primary btn-confirm-folder-delete' data-collection="" data-name="">Delete folder</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="folder-rename">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <h5 class="modal-title">Rename folder in <span id="collection"></span></h5>
                <div class="alert alert-warning" id="alert-panel-folder-rename">
                    <span></span>
                </div>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">New folder name</span>
                  </div>
                  <input type="hidden" id='org-folder-rename-name' value="">
                  <input type="text" class="form-control" id='folder-rename-name' value="">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class='btn btn-primary btn-confirm-folder-rename' data-collection="">Rename folder</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="file-rename">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <h5 class="modal-title">Rename file in <span id="collection"></span></h5>
                <div class="alert alert-warning" id="alert-panel-file-rename">
                    <span></span>
                </div>
                <div class="input-group">
                  <div class="input-group-prepend">
                    <span class="input-group-text">New file name</span>
                  </div>
                  <input type="hidden" id='org-file-rename-name' value="">
                  <input type="text" class="form-control" id='file-rename-name' value="">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class='btn btn-primary btn-confirm-file-rename' data-collection="">Rename file</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="file-delete">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-body">
                <h5 class="modal-title">Delete file in <span id="collection"></span></h5>
                <div class="alert alert-warning" id="alert-panel-file-delete">
                    <span></span>
                </div>
                <p>Do you want to delete file <span id="file-delete-name"></span>?</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class='btn btn-primary btn-confirm-file-delete' data-collection="" data-name="">Delete file</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="uploads">
    <div class="modal-dialog mw-100 w-50">
        <div class="modal-content">
            <div class="modal-body">
                <h5 class="modal-title">Uploads</h5>
                <div id="files"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="viewMedia">
    <div class="modal-dialog mw-100 w-50">
        <div class="modal-content">
            <div class="modal-body text-center">
                <div id="viewer"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php echo $searchHtml; ?>

<div class="row d-block">
    <nav aria-label="breadcrumb flex-column">
        <ol class="breadcrumb">
            <li class="breadcrumb-item">Home</li>
        </ol>
    </nav>

    <div class="top-information">
         <div class="row">
            <div class="col-md-6">
                <h2 class="pt-3"></h2>
            </div>
            <div class="col-md-6">
                <div class="top-info-buttons">
                    <div class="btn-toolbar pull-right" role="toolbar">
                        <div class="btn-group mr-2" role="group">
                            <button type="button" class="btn btn-outline-secondary metadata-form" data-path="" title="Open metadata form">Metadata</button>
                        </div>
                        <div class="btn-group mr-2" role="group">
                            <button type="button" class="btn btn-outline-secondary folder-create" data-path="" title="Create a new folder"><i class="fa fa-folder" aria-hidden="true"></i> Create Folder</button>
                        </div>
                        <div class="btn-group mr-2" role="group">
                            <input type="file" id="upload" multiple style="display: none" />
                            <button type="button" class="btn btn-outline-secondary upload" data-path="" title="Upload files up to 300MB"><i class="fa fa-upload" aria-hidden="true"></i> Upload</button>
                        </div>
                        <div class="btn-group">
                            <div class="dropdown">
                                <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="actionMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    Actions
                                </button>
                                <div class="dropdown-menu action-list" role="menu"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card lock">
            <div class="card-header">Locks</div>
            <div class="list-group lock-items"></div>
        </div>
        <div class="card system-metadata">
            <div class="card-header">System metadata</div>
            <div class="list-group system-metadata-items"></div>
        </div>
        <div class="card actionlog">
            <div class="card-header">Provenance information</div>
            <div class="list-group actionlog-items"></div>
        </div>
    </div>

    <div class="col-md-12">
        <div class="row d-block">
            <table id="file-browser" class="table yoda-table table-striped" ondrop="dropHandler(event);" ondragover="dragOverHandler(event);">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Size</th>
                        <th>Modified date</th>
                        <th></th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
