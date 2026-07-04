        </div> <!-- End of main-content -->
        
        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        
        <!-- Custom JS -->
        <script>
            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                const alerts = document.querySelectorAll('.alert-flash .alert');
                alerts.forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                });
            }, 5000);

            // Form validation (custom, no required attributes)
            (function () {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                const skipTypes = ['hidden', 'submit', 'button', 'reset', 'image'];

                function shouldSkipField(field) {
                    if (!field || field.disabled || field.readOnly) return true;
                    const tag = field.tagName.toLowerCase();
                    if (!['input', 'select', 'textarea'].includes(tag)) return true;
                    const type = (field.getAttribute('type') || '').toLowerCase();
                    if (skipTypes.includes(type)) return true;
                    if (field.dataset.validate === 'false') return true;
                    return false;
                }

                function isAutoRequiredField(field) {
                    const type = (field.getAttribute('type') || '').toLowerCase();
                    if (['checkbox', 'radio'].includes(type)) return false;
                    if (field.dataset.optional === 'true') return false;
                    return true;
                }

                function getFeedbackEl(field) {
                    if (field.nextElementSibling && field.nextElementSibling.classList.contains('invalid-feedback')) {
                        return field.nextElementSibling;
                    }
                    const parent = field.parentElement;
                    if (parent) {
                        const found = parent.querySelector('.invalid-feedback');
                        if (found) return found;
                    }
                    const group = field.closest('.input-group');
                    if (group && group.nextElementSibling && group.nextElementSibling.classList.contains('invalid-feedback')) {
                        return group.nextElementSibling;
                    }
                    // Create feedback element if missing
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback';
                    if (group) {
                        group.insertAdjacentElement('afterend', feedback);
                    } else {
                        field.insertAdjacentElement('afterend', feedback);
                    }
                    return feedback;
                }

                function setValidity(field, message) {
                    const feedback = getFeedbackEl(field);
                    if (message) {
                        field.classList.add('is-invalid');
                        field.classList.remove('is-valid');
                        feedback.textContent = message;
                        feedback.style.display = 'block';
                        return false;
                    }
                    field.classList.remove('is-invalid');
                    field.classList.add('is-valid');
                    feedback.textContent = '';
                    feedback.style.display = 'none';
                    return true;
                }

                function validateField(field) {
                    if (shouldSkipField(field)) return true;
                    const value = (field.type === 'checkbox') ? (field.checked ? '1' : '') : (field.value || '').trim();
                    const form = field.closest('form');
                    const required = field.dataset.required === 'true'
                        || field.hasAttribute('required')
                        || (form && form.dataset.validateAll === 'true' && isAutoRequiredField(field));
                    const type = field.dataset.type || field.getAttribute('type') || field.tagName.toLowerCase();
                    const minLen = field.dataset.minlength || field.getAttribute('minlength');
                    const maxLen = field.dataset.maxlength || field.getAttribute('maxlength');
                    const min = field.dataset.min || field.getAttribute('min');
                    const max = field.dataset.max || field.getAttribute('max');
                    const pattern = field.dataset.pattern || field.getAttribute('pattern');

                    if (required && value === '') {
                        return setValidity(field, field.dataset.msgRequired || 'This field is required.');
                    }

                    if (value !== '') {
                        if (type === 'email' && !emailRegex.test(value)) {
                            return setValidity(field, field.dataset.msgEmail || 'Please enter a valid email address.');
                        }
                        if (type === 'number' && isNaN(Number(value))) {
                            return setValidity(field, field.dataset.msgNumber || 'Please enter a valid number.');
                        }
                        if (type === 'date' && isNaN(Date.parse(value))) {
                            return setValidity(field, field.dataset.msgDate || 'Please enter a valid date.');
                        }
                        if (minLen && value.length < Number(minLen)) {
                            return setValidity(field, field.dataset.msgMinlength || `Please enter at least ${minLen} characters.`);
                        }
                        if (maxLen && value.length > Number(maxLen)) {
                            return setValidity(field, field.dataset.msgMaxlength || `Please enter no more than ${maxLen} characters.`);
                        }
                        if (min !== null && min !== '' && !isNaN(Number(value)) && Number(value) < Number(min)) {
                            return setValidity(field, field.dataset.msgMin || `Value must be at least ${min}.`);
                        }
                        if (max !== null && max !== '' && !isNaN(Number(value)) && Number(value) > Number(max)) {
                            return setValidity(field, field.dataset.msgMax || `Value must be no more than ${max}.`);
                        }
                        if (pattern) {
                            try {
                                const re = new RegExp(pattern);
                                if (!re.test(value)) {
                                    return setValidity(field, field.dataset.msgPattern || 'Please match the requested format.');
                                }
                            } catch (e) {
                                // Ignore invalid pattern
                            }
                        }
                    }

                    return setValidity(field, '');
                }

                document.addEventListener('DOMContentLoaded', function () {
                    const forms = document.querySelectorAll('form');
                    forms.forEach(form => {
                        const isPostForm = (form.getAttribute('method') || 'get').toLowerCase() === 'post';
                        const inModulesPage = window.location.pathname.includes('/modules/');
                        if (inModulesPage && isPostForm && form.dataset.validateAll !== 'false') {
                            form.dataset.validateAll = 'true';
                        }

                        // Convert required attributes to data-required and remove required
                        const requiredFields = form.querySelectorAll('[required]');
                        requiredFields.forEach(field => {
                            field.dataset.required = 'true';
                            field.removeAttribute('required');
                        });

                        form.setAttribute('novalidate', 'novalidate');

                        const fields = form.querySelectorAll('input, select, textarea');
                        fields.forEach(field => {
                            if (shouldSkipField(field)) return;
                            const evt = (field.tagName.toLowerCase() === 'select' || field.type === 'checkbox') ? 'change' : 'input';
                            field.addEventListener(evt, () => validateField(field));
                        });

                        form.addEventListener('submit', function (event) {
                            let valid = true;
                            const toValidate = Array.from(form.querySelectorAll('input, select, textarea'))
                                .filter(field => !shouldSkipField(field));
                            toValidate.forEach(field => {
                                const ok = validateField(field);
                                if (!ok) valid = false;
                            });
                            if (!valid) {
                                event.preventDefault();
                                event.stopPropagation();
                            }
                        });
                    });
                });
            })();
            
            // Confirm before delete
            function confirmDelete(message = 'Are you sure you want to delete this?') {
                return confirm(message);
            }
            
            // Responsive sidebar toggle
            function isMobileViewport() {
                return window.matchMedia('(max-width: 991.98px)').matches;
            }

            function toggleSidebar(forceOpen = null) {
                const body = document.body;

                if (isMobileViewport()) {
                    const shouldOpen = forceOpen === null ? !body.classList.contains('sidebar-open') : !!forceOpen;
                    body.classList.toggle('sidebar-open', shouldOpen);
                    return;
                }

                const shouldCollapse = forceOpen === null ? !body.classList.contains('sidebar-collapsed') : !forceOpen;
                body.classList.toggle('sidebar-collapsed', shouldCollapse);
            }

            function resetSidebarStateForViewport() {
                const body = document.body;
                if (isMobileViewport()) {
                    body.classList.remove('sidebar-collapsed');
                } else {
                    body.classList.remove('sidebar-open');
                }
            }
            
            // Initialize tooltips
            document.addEventListener('DOMContentLoaded', function() {
                resetSidebarStateForViewport();
                window.addEventListener('resize', resetSidebarStateForViewport);

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        document.body.classList.remove('sidebar-open');
                    }
                });

                document.querySelectorAll('.sidebar .nav-link').forEach(function (link) {
                    link.addEventListener('click', function () {
                        if (isMobileViewport()) {
                            document.body.classList.remove('sidebar-open');
                        }
                    });
                });

                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });
        </script>
    </body>
    </html>
