$( document ).ready(function() {
    if ($('#file-browser').length) {
        startBrowsing(browseStartDir, browsePageItems);

        // Rememeber search results
        if (searchTerm.length > 0) {
            search(searchTerm, searchType, browsePageItems, searchStart, searchOrderDir, searchOrderColumn);
        }

    }

    $(".dropdown-menu li a").click(function(){
        searchSelectChanged($(this));
    });

    $('.btn-group button.metadata-form').click(function(){
        showMetadataForm($(this).attr('data-path'));
    });

    $('.btn-group button.folder-status').click(function(){
        toggleFolderStatus($(this).attr('data-status'), $(this).attr('data-path'));

    });

    $(".search-btn").click(function(){
        search($("#search-filter").val(), $("#search_concept").attr('data-type'), $(".search-btn").attr('data-items-per-page'), 0, 'asc', 0);
    });

    $("#search-filter").bind('keypress', function(e) {
        if(e.keyCode==13) {
            search($("#search-filter").val(), $("#search_concept").attr('data-type'), $(".search-btn").attr('data-items-per-page'), 0, 'asc', 0);
        }
    });

    $(".close-search-results").click(function() {
        closeSearchResults();
    });

});

function browse(dir)
{
    var urlDecodedDir = decodeURIComponent((dir + '').replace(/\+/g, '%20'));

    makeBreadcrumb(urlDecodedDir);

    var path = makeBreadcrumbPath(dir);

    changeBrowserUrl(path);
    topInformation(dir);
    buildFileBrowser(dir);
}

function search(value, type, itemsPerPage, displayStart, searchOrderDir, searchOrderColumn)
{
    if (typeof value != 'undefined' && value.length > 0 ) {
        // Display start for first page load
        if (typeof displayStart === 'undefined') {
            displayStart = 0;
        }

        // Table columns definition
        var disableSorting = {};
        var columns = [];
        if (type == 'filename') {
            columns = ['Name', 'Location'];
        } else if (type == 'metadata') {
            columns = ['Location', 'Matches'];
            disableSorting = { 'bSortable': false, 'aTargets': [ -1 ] };
        } else {
            columns = ['Location'];
        }

        // Destroy current Datatable
        var datatable = $('#search').DataTable();
        datatable.destroy();

        var tableHeaders = '';
        $.each(columns, function(i, val){
            tableHeaders += "<th>" + val + "</th>";
        });

        // Create the columns
        $('#search thead tr').html(tableHeaders);

        // Remove table content
        $('#search tbody').remove();

        // Initialize new Datatable
        var url = "browse/search?filter=" + value + "&type=" + type;
        $('#search').DataTable( {
            "bFilter": false,
            "bInfo": false,
            "bLengthChange": false,
            "ajax": {
                "url": url,
                "jsonp": false
            },
            "processing": true,
            "serverSide": true,
            "pageLength": browsePageItems,
            "displayStart": displayStart,
            "drawCallback": function(settings) {
                $( ".browse" ).on( "click", function() {
                    browse($(this).attr('data-path'));
                });

                $('.matches').tooltip();
            },
            "aoColumnDefs": [
                disableSorting
            ],
            "order": [[ searchOrderColumn, searchOrderDir ]]
        });

        $('.search-string').text(value);
        showSearchResults();
    }

    return true;
}

function closeSearchResults()
{
    $('.search-results').hide();
    $('#search-filter').val('');
    $.get("browse/unset_search");
}

function showSearchResults()
{
    $('.search-results').show();
}

function searchSelectChanged(sel)
{
    $("#search_concept").html(sel.text());
    $("#search_concept").attr('data-type', sel.attr('data-type'));
}

function makeBreadcrumb(dir)
{
    var parts = [];
    if (typeof dir != 'undefined') {
        if (dir.length > 0) {
            var elements = dir.split('/');

            // Remove empty elements
            var parts = $.map(elements, function (v) {
                return v === "" ? null : v;
            });
        }
    }

    // Build html
    var totalParts = parts.length;

    if (totalParts > 0 && parts[0]!='undefined') {
        var html = '<li class="browse">Home</li>';
        var path = "";
        $.each( parts, function( k, part ) {
            path += "%2F" + part;

            // Active item
            if (k == (totalParts-1)) {
                html += '<li class="active">' + part.replace(/ /g, "&nbsp;") + '</li>';
            } else {
                html += '<li class="browse" data-path="' + path + '">' + part.replace(/ /g, "&nbsp;") + '</li>';
            }
        });
    } else {
        var html = '<li class="active">Home</li>';
    }

    $('ol.breadcrumb').html(html);
}

function makeBreadcrumbPath(dir)
{
    var parts = [];
    if (typeof dir != 'undefined') {
        if (dir.length > 0) {
            var elements = dir.split('/');

            // Remove empty elements
            var parts = $.map(elements, function (v) {
                return v === "" ? null : v;
            });
        }
    }

    // Build html
    var totalParts = parts.length;
    if (totalParts > 0) {
        var path = "";
        var index = 0;
        $.each( parts, function( k, part ) {
            if(index) {
                path += "/" + part;
            }
            else {
                path = part;
            }
            index++;
        });
    }

    return path;
}


function buildFileBrowser(dir)
{
    var url = "browse/data";
    if (typeof dir != 'undefined') {
        url += "?dir=" +  dir;
    }

    var fileBrowser = $('#file-browser').DataTable();

    fileBrowser.ajax.url(url).load();

    return true;
}

function startBrowsing(path, items)
{
    $('#file-browser').DataTable( {
        "bFilter": false,
        "bInfo": false,
        "bLengthChange": false,
        "ajax": "browse/data",
        "processing": true,
        "serverSide": true,
        "iDeferLoading": 0,
        "pageLength": items,
        "drawCallback": function(settings) {
            $( ".browse" ).on( "click", function() {
                browse($(this).attr('data-path'));
            });
        }
    });

    if (path.length > 0) {
        browse(path);
    } else {
        browse();
    }


}

function changeBrowserUrl(path)
{
    var url = window.location.pathname;
    if (typeof path != 'undefined') {
        url += "?dir=" +  path;
    }

    history.replaceState({} , {}, url);
}

function topInformation(dir)
{
    $('.top-information').hide();
    if (typeof dir != 'undefined') {
        $.getJSON("browse/top_data?dir=" + dir, function(data){
            var icon = '<i class="fa fa-folder-o" aria-hidden="true"></i>';
            var metadata = data.user_metadata;
            var status = data.org_status;
            var userType = data.userType;

            // User metadata
            if (metadata == 'true') {
                $('.btn-group button.metadata-form').attr('data-path', dir);
                $('.btn-group button.metadata-form').show();
            } else {
                $('.btn-group button.metadata-form').hide();
            }

            // folder status
            if (typeof status != 'undefined') {
                if (status == 'UNPROTECTED') {
                    $('.btn-group button.folder-status').text('Unprotected');
                    $('.btn-group button.folder-status').attr('data-status', 'UNPROTECTED');
                } else {
                    $('.btn-group button.folder-status').text('protected');
                    $('.btn-group button.folder-status').attr('data-status', 'PROTECTED');

                    icon = '<span class="fa-stack"><i class="fa fa-folder-o fa-stack-2x"></i><i class="fa fa-shield fa-stack-1x"></i></span>';

                    // Lock position check
                    var rootLock = data.org_root_collection;
                    var path = data.path;
                    if (typeof rootLock != 'undefined') {
                        if (rootLock == path) {
                            $('.btn-group button.folder-status').show();
                        } else {
                            $('.btn-group button.folder-status').hide();
                        }
                    }
                }
                $('.btn-group button.folder-status').attr('data-path', dir);
            }

            if (userType == 'reader') {
                // Hide folder status button
                $('.btn-group button.folder-status').hide();
            }

            $('.top-information h1').html('<span class="icon">' + icon + '</span> ' + data.basename.replace(/ /g, "&nbsp;"));
            $('.top-information').show();
        });
    }
}

function toggleFolderStatus(currentStatus, path)
{
    // Get current button text
    var btnText = $('.btn-group button.folder-status').html();

    // Set spinner & disable button
    $('.btn-group button.folder-status').html(btnText + '<i class="fa fa-spinner fa-spin fa-fw"></i>');
    $('.btn-group button.folder-status').prop("disabled", true);

    if (currentStatus == 'PROTECTED') {
        var newStatus = 'UNPROTECTED';
    } else {
        var newStatus = 'PROTECTED';
    }

    // Change folder status call
    $.getJSON("browse/change_folder_status?path=" + path + "&status=" + newStatus, function(data) {
        if (data.status == 'PROTECTED') {
            $('.btn-group button.folder-status').text('Protected');
            $('.btn-group button.folder-status').attr('data-status', 'PROTECTED');
            var icon = '<span class="fa-stack"><i class="fa fa-folder-o fa-stack-2x"></i><i class="fa fa-shield fa-stack-1x"></i></span>';
        } else {
            $('.btn-group button.folder-status').text('Unprotected');
            $('.btn-group button.folder-status').attr('data-status', 'UNPROTECTED');
            var icon = '<i class="fa fa-folder-o" aria-hidden="true"></i>';
        }

        // Change icon
        $('.top-information h1 .icon').empty().html(icon);

        // Remove disable attribute
        $('.btn-group button.folder-status').removeAttr("disabled");
    });
}

function showMetadataForm(path)
{
    window.location.href = 'metadata/form?path=' + path;
}