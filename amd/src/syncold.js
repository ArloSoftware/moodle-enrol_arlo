import ModalForm from 'core_form/modalform';
import Config from 'core/config';
import {get_string as getString} from 'core/str';

export const init = (linkSelector, formClass) => {
    let syncbutton = document.querySelector(linkSelector);

    syncbutton.addEventListener('click', async (e) => {
        e.preventDefault();

        const form = new ModalForm(({
            formClass,
            args: {},
            modalConfig: {
                title: await getString('dateselector', 'enrol_arlo'),
            },
            saveButtonText: await getString('synchronize', 'enrol_arlo'),
            returnFocus: e.currentTarget
        }));

        form.addEventListener(form.events.FORM_SUBMITTED, (e) => {
            const response = e.detail;
            let redirect = `${Config.wwwroot}/enrol/arlo/admin/apiretries.php?`;
            let first = false;
            for (let key in response) {
                if (first) {
                    first = false;
                } else {
                    redirect = redirect + '&';
                }
                redirect = redirect + `${key}=${response[key]}`;
            }
            window.location.assign(redirect);
        });

        form.show();
    });

};
