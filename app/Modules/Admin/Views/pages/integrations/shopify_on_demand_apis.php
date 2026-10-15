<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="container-fluid py-4" id="shopify-on-demand-apis-page">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
        <div>
            <p class="text-uppercase text-muted small mb-1">Shopify integration</p>
            <h1 class="h4 mb-1">On-demand APIs</h1>
            <p class="text-muted mb-0">Read-only Shopify API actions using the configured store connection.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= base_url('admin/integrations/shopify') ?>">Back to Shopify</a>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white"><h2 class="h6 mb-1">Get Shopify Customer Details</h2><p class="small text-muted mb-0">Read-only lookup of one customer from the configured Shopify store.</p></div>
        <div class="card-body">
            <div class="row small">
                <div class="col-lg-4 mb-3"><div class="text-muted text-uppercase font-weight-bold">Purpose</div><div>Fetches safe customer identity fields only. It does not create or update a ROOTINS patient.</div></div>
                <div class="col-lg-8 mb-3"><div class="text-muted text-uppercase font-weight-bold">ROOTINS admin route</div><code class="text-break">GET /admin/integrations/shopify/on-demand-apis/customers/{customer_id}</code></div>
            </div>
            <hr>
            <h3 class="h6">How ROOTINS calls Shopify</h3>
            <ol class="small text-muted pl-3 mb-3">
                <li>The authenticated Admin request is checked by the <code>shopify.integration.view</code> permission.</li>
                <li>ROOTINS uses the configured store domain, API version, and server-side client-credentials token cache.</li>
                <li>The connection is checked for the configured store and the <code>read_customers</code> scope.</li>
                <li>ROOTINS calls Shopify Admin GraphQL <code>customer(id: $id)</code> and maps only approved identity fields.</li>
                <li>No patient, identity link, order, invoice, payment, or webhook record is created or changed.</li>
            </ol>
            <div class="row small">
                <div class="col-md-6 mb-2"><span class="text-muted">Shopify API:</span> Admin GraphQL over the configured API version</div>
                <div class="col-md-6 mb-2"><span class="text-muted">Required scope:</span> <code>read_customers</code></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white"><h2 class="h6 mb-1">Customer lookup action</h2><p class="small text-muted mb-0">Enter a numeric Shopify customer ID or a Shopify GraphQL customer ID.</p></div>
        <div class="card-body">
            <form id="shopify-customer-lookup-form" class="form-inline">
                <label class="sr-only" for="shopify-customer-lookup-id">Shopify customer ID</label>
                <input class="form-control mr-2 mb-2" id="shopify-customer-lookup-id" name="customer_id" placeholder="Customer ID or gid://shopify/Customer/..." required maxlength="80" autocomplete="off">
                <button class="btn btn-primary mb-2" type="submit" id="shopify-customer-lookup-submit">Get Customer Details</button>
            </form>
            <p class="small text-muted mt-2 mb-0">The store domain is taken from the server-side Shopify configuration.</p>
        </div>
    </div>

    <div class="modal fade" id="shopify-customer-lookup-modal" tabindex="-1" role="dialog" aria-labelledby="shopify-customer-lookup-title" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title" id="shopify-customer-lookup-title">Shopify Customer Details</h5><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div>
                <div class="modal-body" id="shopify-customer-lookup-result" aria-live="polite"><div class="text-muted">Enter a customer ID to begin.</div></div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button><button type="button" class="btn btn-outline-primary" id="shopify-customer-lookup-copy" disabled>Copy Customer ID</button></div>
            </div>
        </div>
    </div>
</main>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>window.rootinsShopifyCustomerLookupUrl = <?= json_encode(base_url('admin/integrations/shopify/on-demand-apis/customers')) ?>;</script>
<script src="<?= base_url('assets/js/admin/integrations/shopify-customer-lookup.js') ?>"></script>
<?= $this->endSection() ?>
