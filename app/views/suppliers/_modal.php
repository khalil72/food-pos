<div class="modal fade" id="supplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('suppliers/save') ?>" data-fill-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="0" data-reset="0">
            <div class="modal-header"><h5 class="modal-title" data-modal-title data-create="Add supplier" data-edit="Edit supplier">Add supplier</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label required" for="sName">Company name</label><input class="form-control" id="sName" name="name" maxlength="120" required></div>
                    <div class="col-6"><label class="form-label" for="sContact">Contact person</label><input class="form-control" id="sContact" name="contact_person" maxlength="100"></div>
                    <div class="col-6"><label class="form-label" for="sPhone">Phone</label><input class="form-control" id="sPhone" name="phone" maxlength="30"></div>
                    <div class="col-12"><label class="form-label" for="sEmail">Email</label><input type="email" class="form-control" id="sEmail" name="email" maxlength="150"></div>
                    <div class="col-12"><label class="form-label" for="sAddress">Address</label><input class="form-control" id="sAddress" name="address" maxlength="255"></div>
                    <div class="col-12"><label class="form-label" for="sNotes">Notes</label><textarea class="form-control" id="sNotes" name="notes" rows="2" maxlength="1000" placeholder="Delivery days, payment terms…"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save supplier</button></div>
        </form>
    </div>
</div>
