<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" method="post" action="<?= url('customers/save') ?>" data-fill-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="0" data-reset="0">
            <div class="modal-header"><h5 class="modal-title" data-modal-title data-create="Add customer" data-edit="Edit customer">Add customer</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label required" for="cuName">Full name</label><input class="form-control" id="cuName" name="name" maxlength="120" required></div>
                    <div class="col-6"><label class="form-label" for="cuPhone">Phone</label><input class="form-control" id="cuPhone" name="phone" maxlength="30" inputmode="tel"></div>
                    <div class="col-6"><label class="form-label" for="cuEmail">Email</label><input type="email" class="form-control" id="cuEmail" name="email" maxlength="150"></div>
                    <div class="col-12"><label class="form-label" for="cuAddress">Address</label><input class="form-control" id="cuAddress" name="address" maxlength="255"></div>
                    <div class="col-12"><label class="form-label" for="cuNotes">Notes</label><textarea class="form-control" id="cuNotes" name="notes" rows="2" maxlength="1000" placeholder="Allergies, preferences…"></textarea></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save customer</button></div>
        </form>
    </div>
</div>
