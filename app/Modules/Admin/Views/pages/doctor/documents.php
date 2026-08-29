<?= $this->extend(ADMIN_VIEW.'layout') ?>


<?= $this->section('main-content') ?>

<main class="dashboard-container">

    <!-- breadcrumb -->
    <?= view(
        'templates/_breadcrumb',
        [
            'module'      => 'admin',
            'menu_title'  => 'Doctor Management',
            'page'        => 'Doctor Documents'
        ]
    ) ?>


    <!-- main content -->
    <div class="action-btn-container">

        <button class="btn btn-sm theme-btn open_doctor_document_modal">
            + New Doctor Document
        </button>

    </div>


    <?= display_session_msg() ?>


    <div class="table-responsive">

        <table
            class="table table-bordered table-hover align-middle"
            id="doctorDocumentTable">

            <thead>

                <tr>

                    <th width="50">#</th>

                    <th>Doctor</th>

                    <th>Document Type</th>

                    <th>Document Name</th>

                    <th>File</th>

                    <th>Size</th>

                    <th>Status</th>

                    <th>Uploaded On</th>

                    <th width="150">Action</th>

                </tr>

            </thead>


            <tbody>

            <?php if (!empty($documents)): ?>

                <?php foreach ($documents as $key => $row): ?>

                    <tr>

                        <!-- # -->
                        <td>
                            <?= $key + 1 ?>
                        </td>


                        <!-- Doctor -->
                        <td>

                            <strong>

                                Dr.
                                <?= esc(
                                    trim(
                                        $row['first_name'] . ' ' .
                                        $row['last_name']
                                    )
                                ) ?>

                            </strong>

                            <br>

                            <small class="text-muted">

                                <?= esc($row['doctor_code']) ?>

                            </small>

                        </td>


                        <!-- Document Type -->
                        <td>

                            <?php

                            $documentTypeLabels = [

                                'REGISTRATION' => 'Registration Certificate',
                                'DEGREE'       => 'Degree / Qualification',
                                'LICENSE'      => 'Medical License',
                                'ID_PROOF'     => 'ID Proof',
                                'EXPERIENCE'   => 'Experience Certificate',
                                'OTHER'        => 'Other',

                            ];

                            ?>

                            <span>

                                <?= esc(
                                    $documentTypeLabels[
                                        $row['document_type']
                                    ]
                                    ?? $row['document_type']
                                ) ?>

                            </span>

                        </td>


                        <!-- Document Name -->
                        <td>

                            <strong>

                                <?= esc($row['document_name']) ?>

                            </strong>

                            <?php if (!empty($row['remarks'])): ?>

                                <br>

                                <small class="text-muted">

                                    <?= esc($row['remarks']) ?>

                                </small>

                            <?php endif; ?>

                        </td>


                        <!-- File -->
                        <td>

                            <i class="fa fa-file-o"></i>

                            <?= esc($row['file_name']) ?>

                            <br>

                            <small class="text-muted">

                                <?= esc(
                                    strtoupper(
                                        pathinfo(
                                            $row['file_name'],
                                            PATHINFO_EXTENSION
                                        )
                                    )
                                ) ?>

                            </small>

                        </td>


                        <!-- File Size -->
                        <td>

                            <?php

                            $fileSize = (int) $row['file_size'];

                            if ($fileSize >= 1048576) {

                                echo number_format(
                                    $fileSize / 1048576,
                                    2
                                ) . ' MB';

                            } elseif ($fileSize >= 1024) {

                                echo number_format(
                                    $fileSize / 1024,
                                    2
                                ) . ' KB';

                            } else {

                                echo $fileSize . ' B';

                            }

                            ?>

                        </td>


                        <!-- Status -->
                        <td>

                            <?php if ((int) $row['status'] === 1): ?>

                                <span class="badge bg-success">

                                    Active

                                </span>

                            <?php else: ?>

                                <span class="badge bg-secondary">

                                    Inactive

                                </span>

                            <?php endif; ?>

                        </td>


                        <!-- Uploaded On -->
                        <td>

                            <?= !empty($row['created_at'])
                                ? date(
                                    'd-m-Y',
                                    strtotime($row['created_at'])
                                )
                                : '-'
                            ?>

                        </td>


                        <!-- Actions -->
                        <td>

                            <div class="d-flex gap-1">

                                <!-- Download -->
                                <a
                                    href="<?= base_url(
                                        'admin/doctor-documents/download/' .
                                        $row['id']
                                    ) ?>"
                                    class="btn btn-sm btn-success"
                                    title="Download">

                                    <i class="fa fa-download"></i>

                                </a>


                                <!-- Edit -->
                                <button
                                    class="btn btn-sm btn-primary"
                                    title="Edit"
                                    onclick="editDoctorDocumentForm(<?= $row['id'] ?>)">

                                    <i class="fa fa-edit"></i>

                                </button>


                                <!-- Delete -->
                                <a
                                    href="<?= base_url(
                                        'admin/doctor-documents/delete/' .
                                        $row['id']
                                    ) ?>"
                                    class="btn btn-sm btn-danger"
                                    title="Delete"
                                    onclick="return confirm('Are you sure you want to delete this document?')">

                                    <i class="bi bi-trash"></i>

                                </a>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>


            <?php else: ?>

                <tr>

                    <td
                        colspan="9"
                        class="text-center text-muted py-4">

                        No doctor documents found.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</main>


<?= view(
    ADMIN_VIEW.'pages/doctor/modals/_document'
) ?>


<?= $this->section('scripts') ?>

<script src="<?= base_url(
    'assets/js/admin/doctor/doctor_document.js'
) ?>"></script>

<?= $this->endSection() ?>


<?= $this->endSection() ?>