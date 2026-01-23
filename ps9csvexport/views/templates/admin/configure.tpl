{if isset($smarty.get.debug) && $smarty.get.debug==1}<div class="alert alert-info"><strong>DEBUG:</strong> baseUrl = {$baseUrl|escape:'html':'UTF-8'}</div>{/if}

<div class="panel">
    <div class="panel-heading"><i class="icon-download"></i> {l s='Export CSV for PrestaShop 9' mod='ps9csvexport'}</div>
    
    <div class="panel-body">
        <h4>{l s='Select entity to export' mod='ps9csvexport'}</h4>
        
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Export type' mod='ps9csvexport'}</label>
            <div class="col-lg-9">
                <select id="export_type" class="form-control">
                    <option value="products">{l s='Products' mod='ps9csvexport'}</option>
                    <option value="categories">{l s='Categories' mod='ps9csvexport'}</option>
                    <option value="brands">{l s='Brands' mod='ps9csvexport'}</option>
                    <option value="suppliers">{l s='Suppliers' mod='ps9csvexport'}</option>
                    <option value="customers">{l s='Customers' mod='ps9csvexport'}</option>
                    <option value="addresses">{l s='Addresses' mod='ps9csvexport'}</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <div class="col-lg-12">
                <button type="button" class="btn btn-primary" onclick="exportCSV()">
                    <i class="icon-download"></i> {l s='Export' mod='ps9csvexport'}
                </button>
            </div>
        </div>
        
        <div id="exportResults"></div>
    </div>
</div>

<div class="panel">
    <div class="panel-heading"><i class="icon-upload"></i> {l s='Import CSV to PrestaShop 9' mod='ps9csvexport'}</div>
    
    <div class="panel-body">
        <div class="alert alert-warning">
            <strong>{l s='IMPORTANT' mod='ps9csvexport'}:</strong> {l s='Import order' mod='ps9csvexport'}: 1) Categories, 2) Brands/Suppliers, 3) Products, 4) Customers, 5) Addresses
        </div>
        
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Upload CSV file' mod='ps9csvexport'}</label>
            <div class="col-lg-9">
                <input type="file" id="csv_file" accept=".csv" class="form-control">
            </div>
        </div>
        
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Entity type' mod='ps9csvexport'}</label>
            <div class="col-lg-9">
                <select id="import_type" class="form-control">
                    <option value="categories">{l s='Categories' mod='ps9csvexport'}</option>
                    <option value="brands">{l s='Brands' mod='ps9csvexport'}</option>
                    <option value="suppliers">{l s='Suppliers' mod='ps9csvexport'}</option>
                    <option value="products">{l s='Products' mod='ps9csvexport'}</option>
                    <option value="customers">{l s='Customers' mod='ps9csvexport'}</option>
                    <option value="addresses">{l s='Addresses' mod='ps9csvexport'}</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label class="control-label col-lg-3">{l s='Delete existing data' mod='ps9csvexport'}</label>
            <div class="col-lg-9">
                <input type="checkbox" id="truncate_table"> <small class="text-danger">{l s='WARNING: This will delete all existing data!' mod='ps9csvexport'}</small>
            </div>
        </div>
        
        <div class="form-group">
            <div class="col-lg-12">
                <button type="button" class="btn btn-primary" onclick="uploadAndImport()">
                    <i class="icon-upload"></i> {l s='Upload & Import' mod='ps9csvexport'}
                </button>
                <button type="button" class="btn btn-default" onclick="importFromServer()">
                    <i class="icon-upload"></i> {l s='Import from server' mod='ps9csvexport'}
                </button>
            </div>
        </div>
        
        <div id="importResults"></div>
    </div>
</div>

<div class="panel">
    <div class="panel-heading"><i class="icon-list"></i> {l s='Exported files' mod='ps9csvexport'}</div>
    <div class="panel-body">
        <button type="button" class="btn btn-default btn-sm" onclick="listFiles()">
            <i class="icon-refresh"></i> {l s='Refresh' mod='ps9csvexport'}
        </button>
        <div id="filesList" style="margin-top: 15px;"></div>
    </div>
</div>

<script type="text/javascript">
var baseUrl = '{$baseUrl|escape:'quotes':'UTF-8'}';

function exportCSV() {
    var exportType = $('#export_type').val();
    $('#exportResults').html('<div class="alert alert-info"><i class="icon-spinner icon-spin"></i> Exporting...</div>');
    
    $.ajax({
        url: baseUrl,
        method: 'POST',
        data: {
            ajax: '1',
            action: 'exportCSV',
            export_type: exportType,
            shop_id: {$defaultShopId|intval},
            lang_id: {$defaultLangId|intval}
        },
        dataType: 'json',
        success: function(response) {
            if (response.ok) {
                $('#exportResults').html(
                    '<div class="alert alert-success">' +
                    '<strong>Success!</strong> File: ' + response.filename +
                    '<br>Records: ' + response.count +
                    '</div>'
                );
                listFiles();
            } else {
                $('#exportResults').html('<div class="alert alert-danger"><strong>Error:</strong> ' + (response.error || 'Unknown error') + '</div>');
            }
        },
        error: function(xhr) {
            $('#exportResults').html('<div class="alert alert-danger"><strong>Error:</strong> ' + xhr.status + ' - ' + xhr.responseText + '</div>');
        }
    });
}

function uploadAndImport() {
    var fileInput = $('#csv_file')[0];
    if (!fileInput.files.length) {
        alert('Please select a CSV file');
        return;
    }
    
    var formData = new FormData();
    formData.append('csv_file', fileInput.files[0]);
    formData.append('ajax', '1');
    formData.append('action', 'uploadFile');
    
    $('#importResults').html('<div class="alert alert-info"><i class="icon-spinner icon-spin"></i> Uploading...</div>');
    
    $.ajax({
        url: baseUrl,
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.ok) {
                importFile(response.filename);
            } else {
                $('#importResults').html('<div class="alert alert-danger"><strong>Error:</strong> ' + response.error + '</div>');
            }
        },
        error: function(xhr) {
            $('#importResults').html('<div class="alert alert-danger"><strong>Error uploading:</strong> ' + xhr.responseText + '</div>');
        }
    });
}

function importFromServer() {
    var filename = prompt('Enter filename from server (e.g., categories_csv_20260112_123456.csv):');
    if (!filename) return;
    
    importFile(filename);
}

function importFile(filename) {
    var entityType = $('#import_type').val();
    var truncate = $('#truncate_table').is(':checked');
    
    $('#importResults').html('<div class="alert alert-info"><i class="icon-spinner icon-spin"></i> Importing ' + filename + '...</div>');
    
    $.ajax({
        url: baseUrl,
        method: 'POST',
        data: {
            ajax: '1',
            action: 'importCSV',
            filename: filename,
            entity_type: entityType,
            truncate: truncate ? '1' : '0'
        },
        dataType: 'json',
        success: function(response) {
            if (response.ok) {
                var html = '<div class="alert alert-success">' +
                    '<strong>Import complete!</strong><br>' +
                    'Imported: ' + response.imported + '<br>' +
                    'Skipped: ' + response.skipped + '<br>';
                
                if (response.errors.length > 0) {
                    html += '<hr><strong>Errors:</strong><br><ul>';
                    $.each(response.errors, function(i, err) {
                        html += '<li>' + err + '</li>';
                    });
                    html += '</ul>';
                }
                
                if (response.warnings.length > 0) {
                    html += '<hr><strong>Warnings:</strong><br><ul>';
                    $.each(response.warnings, function(i, warn) {
                        html += '<li>' + warn + '</li>';
                    });
                    html += '</ul>';
                }
                
                html += '</div>';
                $('#importResults').html(html);
            } else {
                $('#importResults').html('<div class="alert alert-danger"><strong>Error:</strong> ' + response.error + '</div>');
            }
        },
        error: function(xhr) {
            $('#importResults').html('<div class="alert alert-danger"><strong>Error:</strong> ' + xhr.responseText + '</div>');
        }
    });
}

function listFiles() {
    $.ajax({
        url: baseUrl,
        method: 'POST',
        data: { ajax: '1', action: 'listFiles' },
        dataType: 'json',
        success: function(response) {
            if (response.ok && response.files.length > 0) {
                var html = '<table class="table"><thead><tr><th>File</th><th>Size</th><th>Date</th><th>Actions</th></tr></thead><tbody>';
                $.each(response.files, function(i, file) {
                    var size = (file.size / 1024).toFixed(2) + ' KB';
                    html += '<tr><td>' + file.name + '</td><td>' + size + '</td><td>' + file.date + '</td>' +
                            '<td><a href="' + baseUrl + '&downloadPs9Export=1&file=' + encodeURIComponent(file.name) + '" class="btn btn-default btn-sm"><i class="icon-download"></i> Download</a> ' +
                            '<button onclick="deleteFile(\'' + file.name + '\')" class="btn btn-danger btn-sm"><i class="icon-trash"></i> Delete</button></td></tr>';
                });
                html += '</tbody></table>';
                $('#filesList').html(html);
            } else {
                $('#filesList').html('<p class="text-muted">No files found.</p>');
            }
        }
    });
}

function deleteFile(filename) {
    if (!confirm('Delete ' + filename + '?')) return;
    
    $.ajax({
        url: baseUrl,
        method: 'POST',
        data: { ajax: '1', action: 'deleteFile', file: filename },
        dataType: 'json',
        success: function(response) {
            if (response.ok) listFiles();
            else alert('Error: ' + response.error);
        }
    });
}

$(document).ready(function() {
    listFiles();
});
</script>
