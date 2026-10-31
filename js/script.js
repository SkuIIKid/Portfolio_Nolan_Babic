// ===== Initialisation des icônes Lucide =====
lucide.createIcons();

// ===== Formulaire de contact =====
(function () {
    const form = document.getElementById('contact-form');
    if (!form) return;

    const statusBox = document.getElementById('form-status');
    const submitBtn = document.getElementById('submit-btn');
    const submitLabel = document.getElementById('submit-label');
    const counter = document.getElementById('message-counter');
    const messageField = form.elements['message'];

    const MESSAGE_MAX = 2000;
    const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

    // Compteur de caractères
    messageField.addEventListener('input', () => {
        counter.textContent = messageField.value.length + ' / ' + MESSAGE_MAX;
    });

    function setError(field, message) {
        field.classList.add('is-invalid');
        const err = document.querySelector('[data-error-for="' + field.name + '"]');
        if (err) err.textContent = message;
    }

    function clearErrors() {
        form.querySelectorAll('.form-field').forEach((f) => f.classList.remove('is-invalid'));
        form.querySelectorAll('.field-error').forEach((e) => (e.textContent = ''));
        statusBox.className = 'form-status';
        statusBox.textContent = '';
    }

    function showStatus(type, message) {
        statusBox.className = 'form-status ' + type;
        statusBox.textContent = message;
    }

    // Validation côté client (confort utilisateur ; la vraie validation est faite en PHP)
    function validate() {
        let valid = true;
        const name = form.elements['name'];
        const email = form.elements['email'];
        const subject = form.elements['subject'];
        const message = form.elements['message'];

        if (name.value.trim().length < 2) {
            setError(name, 'Merci d\'indiquer votre nom (2 caractères minimum).');
            valid = false;
        }
        if (!EMAIL_REGEX.test(email.value.trim())) {
            setError(email, 'Adresse e-mail invalide.');
            valid = false;
        }
        if (subject.value.trim().length < 3) {
            setError(subject, 'Merci d\'indiquer un objet (3 caractères minimum).');
            valid = false;
        }
        if (message.value.trim().length < 10) {
            setError(message, 'Votre message est trop court (10 caractères minimum).');
            valid = false;
        } else if (message.value.length > MESSAGE_MAX) {
            setError(message, 'Votre message est trop long (' + MESSAGE_MAX + ' caractères maximum).');
            valid = false;
        }
        return valid;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearErrors();

        if (!validate()) return;

        submitBtn.classList.add('btn-loading');
        submitLabel.textContent = 'Envoi en cours...';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'Accept': 'application/json' }
            });

            const data = await response.json();

            if (response.ok && data.success) {
                showStatus('success', data.message);
                form.reset();
                counter.textContent = '0 / ' + MESSAGE_MAX;
            } else {
                // Erreurs de validation renvoyées par le serveur
                if (data.errors) {
                    Object.keys(data.errors).forEach((fieldName) => {
                        const field = form.elements[fieldName];
                        if (field) setError(field, data.errors[fieldName]);
                    });
                }
                showStatus('error', data.message || 'Une erreur est survenue. Merci de réessayer.');
            }
        } catch (err) {
            showStatus('error', 'Impossible de joindre le serveur. Vérifiez votre connexion ou écrivez-moi directement par e-mail.');
        } finally {
            submitBtn.classList.remove('btn-loading');
            submitLabel.textContent = 'Envoyer le message';
        }
    });
})();
