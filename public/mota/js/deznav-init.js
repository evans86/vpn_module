function getUrlParams(dParam) {
    var dPageURL = window.location.search.substring(1),
        dURLVariables = dPageURL.split('&'),
        dParameterName,
        i;

    for (i = 0; i < dURLVariables.length; i++) {
        dParameterName = dURLVariables[i].split('=');

        if (dParameterName[0] === dParam) {
            return dParameterName[1] === undefined ? true : decodeURIComponent(dParameterName[1]);
        }
    }
}

(function($) {
    var direction = getUrlParams('dir');
    if(direction != 'rtl') {
        direction = 'ltr';
    }

    var dezSettings = {
        direction: direction,
        layout: 'vertical',
        headerPosition: 'fixed',
        sidebarPosition: 'fixed',
        sidebarStyle: 'full'
    };

    // Extend existing settings with new options
    if (window.dezSettings) {
        window.dezSettings = {
            ...window.dezSettings,
            ...dezSettings
        };
    } else {
        window.dezSettings = dezSettings;
    }
    
    function initializeLayout() {
        // Apply layout settings
        if (window.dezSettings.layout === 'vertical') {
            $('.deznav').addClass('vertical-menu');
            $('.deznav').removeClass('horizontal-menu');
        }
        
        // Apply header position
        if (window.dezSettings.headerPosition === 'fixed') {
            $('.header').addClass('is-fixed');
            $('.header').removeClass('is-static');
        }
        
        // Apply sidebar position
        if (window.dezSettings.sidebarPosition === 'fixed') {
            $('.deznav').addClass('fixed');
            $('.deznav').removeClass('static');
        }
        
        // Apply sidebar style
        if (window.dezSettings.sidebarStyle === 'full') {
            $('.deznav').addClass('full-menu');
            $('.deznav').removeClass('mini-menu');
        }

        // Apply direction
        if (window.dezSettings.direction === 'rtl') {
            $('html').attr('dir', 'rtl');
            $('body').addClass('rtl');
        }

        // Initialize tooltips
        $('[data-toggle="tooltip"]').tooltip();

        // Add hover effects
        $('.nav-link').hover(
            function() {
                $(this).addClass('hover');
            },
            function() {
                $(this).removeClass('hover');
            }
        );

        // Handle menu item click
        $('.nav-link').on('click', function() {
            $('.nav-link').removeClass('active');
            $(this).addClass('active');
        });

        // Handle responsive menu
        function handleResponsiveMenu() {
            if (window.innerWidth <= 768) {
                $('.deznav').addClass('mini-menu');
                $('.deznav').removeClass('full-menu');
            } else {
                if (window.dezSettings.sidebarStyle === 'full') {
                    $('.deznav').removeClass('mini-menu');
                    $('.deznav').addClass('full-menu');
                }
            }
        }

        // Initial check
        handleResponsiveMenu();

        // Listen for window resize
        $(window).resize(function() {
            handleResponsiveMenu();
        });
    }

    // Initialize on document ready
    $(document).ready(function() {
        initializeLayout();
    });

})(jQuery);