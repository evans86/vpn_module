class dezSettings {
    constructor(settings) {
        this.typography = settings.typography || 'poppins';
        this.version = settings.version || 'light';
        this.layout = settings.layout || 'vertical';
        this.headerBg = settings.headerBg || 'color_1';
        this.navheaderBg = settings.navheaderBg || 'color_1';
        this.sidebarBg = settings.sidebarBg || 'color_1';
        this.sidebarStyle = settings.sidebarStyle || 'full';
        this.sidebarPosition = settings.sidebarPosition || 'fixed';
        this.headerPosition = settings.headerPosition || 'fixed';
        this.containerLayout = settings.containerLayout || 'full';
        this.direction = settings.direction || 'ltr';

        this.manageTypography();
        this.manageVersion();
        this.manageLayout();
        this.manageNavHeaderBg();
        this.manageSidebarStyle();
        this.manageSidebarBg();
        this.manageSidebarPosition();
        this.manageHeaderPosition();
        this.manageContainerLayout();
        this.manageRtlLayout();
        this.manageResponsiveSidebar();
    }

    manageVersion() {
        switch(this.version) {
            case "light": 
                $("body").attr("data-theme-version", "light");
                break;
            case "dark": 
                $("body").attr("data-theme-version", "dark");
                break;
            default: 
                $("body").attr("data-theme-version", "light");
        }
    }

    manageTypography() {
        $("body").attr("data-typography", this.typography);
    }

    manageLayout() {
        switch(this.layout) {
            case "horizontal":
                $("body").attr("data-layout", "horizontal");
                break;
            case "vertical": 
                $("body").attr("data-layout", "vertical");
                break;
            default: 
                $("body").attr("data-layout", "vertical");
        }
    }

    manageNavHeaderBg() {
        $("body").attr("data-navheader-bg", this.navheaderBg);
    }

    manageSidebarStyle() {
        switch(this.sidebarStyle) {
            case "full":
                $("body").attr("data-sidebar-style", "full");
                break;
            case "mini":
                $("body").attr("data-sidebar-style", "mini");
                break;
            case "overlay": 
                $("body").attr("data-sidebar-style", "overlay");
                break;
            default: 
                $("body").attr("data-sidebar-style", "full");
        }
    }

    manageSidebarBg() {
        $("body").attr("data-sibebarbg", this.sidebarBg);
    }

    manageSidebarPosition() {
        switch(this.sidebarPosition) {
            case "fixed":
                $("body").attr("data-sidebar-position", "fixed");
                break;
            case "static": 
                $("body").attr("data-sidebar-position", "static");
                break;
            default: 
                $("body").attr("data-sidebar-position", "fixed");
        }
    }

    manageHeaderPosition() {
        switch(this.headerPosition) {
            case "fixed":
                $("body").attr("data-header-position", "fixed");
                break;
            case "static": 
                $("body").attr("data-header-position", "static");
                break;
            default: 
                $("body").attr("data-header-position", "fixed");
        }
    }

    manageContainerLayout() {
        switch(this.containerLayout) {
            case "boxed":
                $("body").attr("data-container", "boxed");
                break;
            case "wide": 
                $("body").attr("data-container", "wide");
                break;
            default: 
                $("body").attr("data-container", "wide");
        }
    }

    manageRtlLayout() {
        switch(this.direction) {
            case "rtl":
                $("html").attr("dir", "rtl");
                $("body").attr("direction", "rtl");
                break;
            case "ltr": 
                $("html").attr("dir", "ltr");
                $("body").attr("direction", "ltr");
                break;
            default: 
                $("html").attr("dir", "ltr");
                $("body").attr("direction", "ltr");
        }
    }

    manageResponsiveSidebar() {
        const innerWidth = $(window).innerWidth();
        if (innerWidth < 1200) {
            $("body").attr("data-layout", "vertical");
            $("body").attr("data-container", "wide");
        }

        if (innerWidth > 767 && innerWidth < 1200) {
            $("body").attr("data-sidebar-style", "mini");
        }

        if (innerWidth < 768) {
            $("body").attr("data-sidebar-style", "overlay");
        }
    }
}
