<div class="panel">
    <div class="panel-heading">
        <i class="icon-info-circle"></i> {l s='Migration Instructions' mod='ps178to9migration'}
    </div>
    <div class="panel-body">
        <div class="alert alert-info">
            <h4>{l s='How to use this module:' mod='ps178to9migration'}</h4>
            <ol>
                <li>{l s='Select the export format (JSON, SQL, or CSV)' mod='ps178to9migration'}</li>
                <li>{l s='Choose single file or multiple files export mode' mod='ps178to9migration'}</li>
                <li>{l s='Select specific tables or export all compatible tables' mod='ps178to9migration'}</li>
                <li>{l s='Click "Generate Export" to download your data' mod='ps178to9migration'}</li>
            </ol>
        </div>
        
        <div class="alert alert-warning">
            <h4><i class="icon-warning"></i> {l s='Important Notes:' mod='ps178to9migration'}</h4>
            <ul>
                <li>{l s='Always backup your database before migration' mod='ps178to9migration'}</li>
                <li>{l s='Test the import on a staging environment first' mod='ps178to9migration'}</li>
                <li>{l s='This module does not export media files (images, PDFs, etc.)' mod='ps178to9migration'}</li>
                <li>{l s='Third-party module data may require manual migration' mod='ps178to9migration'}</li>
            </ul>
        </div>
    </div>
</div>

{$content nofilter}
