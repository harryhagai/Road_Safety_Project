(function () {
    if (window.mailSettingsInit === true) {
        return;
    }

    window.mailSettingsInit = true;

    const syncPurposeOtherInput = function (prefix, selectedValue) {
        const wrapper = document.querySelector('[data-mail-purpose-other-wrapper="' + prefix + '"]');
        const input = document.querySelector('[data-mail-purpose-other-input="' + prefix + '"]');
        if (!wrapper || !input) {
            return;
        }

        const isOther = selectedValue === 'other';
        wrapper.classList.toggle('d-none', !isOther);
        input.required = isOther;
        if (!isOther) {
            input.value = '';
        }
    };

    ['create-', 'edit-'].forEach(function (prefix) {
        const select = document.querySelector('[data-mail-purpose-select="' + prefix + '"]');
        if (!select) {
            return;
        }
        syncPurposeOtherInput(prefix, select.value);
    });

    document.addEventListener('click', function (event) {
        const editButton = event.target.closest('.js-mail-edit');
        const deleteButton = event.target.closest('.js-mail-delete');
        const passwordToggle = event.target.closest('[data-mail-password-toggle]');

        if (editButton) {
            const setting = JSON.parse(editButton.dataset.mail);
            document.getElementById('editMailSettingForm').action = setting.update_url;
            document.getElementById('edit-name').value = setting.name ?? '';
            const editPurpose = document.getElementById('edit-purpose');
            const knownPurpose = Array.from(editPurpose.options).some(function (opt) {
                return opt.value === setting.purpose;
            });
            editPurpose.value = knownPurpose ? setting.purpose : 'other';
            const editPurposeOther = document.getElementById('edit-purpose-other');
            if (editPurposeOther) {
                editPurposeOther.value = knownPurpose ? '' : (setting.purpose ?? '');
            }
            syncPurposeOtherInput('edit-', editPurpose.value);
            document.getElementById('edit-mailer').value = setting.mailer ?? 'smtp';
            document.getElementById('edit-scheme').value = setting.scheme ?? '';
            document.getElementById('edit-host').value = setting.host ?? '';
            document.getElementById('edit-port').value = setting.port ?? 2525;
            document.getElementById('edit-username').value = setting.username ?? '';
            document.getElementById('edit-password').type = 'password';
            document.getElementById('edit-password').value = setting.password ?? '';
            const editPasswordToggleIcon = document.querySelector('[data-mail-password-toggle="edit-password"] i');
            if (editPasswordToggleIcon) {
                editPasswordToggleIcon.classList.add('bi-eye');
                editPasswordToggleIcon.classList.remove('bi-eye-slash');
            }
            document.getElementById('edit-from-address').value = setting.from_address ?? '';
            document.getElementById('edit-from-name').value = setting.from_name ?? '';
            document.getElementById('edit-is-active').checked = Boolean(setting.is_active);
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('editMailSettingModal')).show();
        }

        if (deleteButton) {
            const setting = JSON.parse(deleteButton.dataset.mail);
            document.getElementById('deleteMailSettingForm').action = setting.delete_url;
            document.querySelector('[data-mail-delete="name"]').textContent = setting.name || '';
            window.bootstrap.Modal.getOrCreateInstance(document.getElementById('deleteMailSettingModal')).show();
        }

        if (passwordToggle) {
            const input = document.getElementById(passwordToggle.dataset.mailPasswordToggle);
            const icon = passwordToggle.querySelector('i');

            if (!input || !icon) {
                return;
            }

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            icon.classList.toggle('bi-eye', !isHidden);
            icon.classList.toggle('bi-eye-slash', isHidden);
        }
    });

    document.addEventListener('change', function (event) {
        const select = event.target.closest('[data-mail-purpose-select]');
        if (!select) {
            return;
        }
        syncPurposeOtherInput(select.dataset.mailPurposeSelect, select.value);
    });
})();
