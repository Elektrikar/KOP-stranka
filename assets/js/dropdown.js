$(document).ready(function () {
    const dropdownToggle = $('#userDropdownToggle');
    const dropdownContent = $('#userDropdownContent');
    const dropdownOverlay = $('#dropdownOverlay');

    dropdownToggle.on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        const isActive = dropdownContent.hasClass('show');

        closeDropdown();

        if (!isActive) {
            dropdownOverlay.addClass('active');
            dropdownContent[0].offsetHeight;
            dropdownContent.addClass('show');
            dropdownToggle.addClass('active');
        }
    });

    // Close dropdown when clicking on overlay
    dropdownOverlay.on('click', function () {
        closeDropdown();
    });

    // Close dropdown when clicking outside
    $(document).on('click', function (e) {
        if (!$(e.target).closest('.user-dropdown').length) {
            closeDropdown();
        }
    });

    // Close dropdown when clicking on a link inside it
    dropdownContent.on('click', 'a', function () {
        // Don't close immediately for logout confirmation
        if (!$(this).hasClass('logout-btn')) {
            closeDropdown();
        }
    });

    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closeDropdown();
        }
    });

    function closeDropdown() {
        dropdownContent.removeClass('show');
        dropdownToggle.removeClass('active');

        // Wait for transition to complete before hiding overlay
        setTimeout(() => {
            dropdownOverlay.removeClass('active');
        }, 200);
    }
});