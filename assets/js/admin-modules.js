(function($){
    $(document).ready(function(){
        // console.log('=== ADMIN MODULES JS LOADED ===');
        
        // 危险级开关确认对话框 - 保留这个功能
        $(document).on('change', '.w2p-danger-toggle', function(e){
            if ($(this).is(':checked')) {
                var confirmMessage = '警告：启用此功能将阻止所有外部HTTP请求，包括插件和主题的更新检查。这会显著加快后台加载速度，但可能导致某些功能失效。\n\n您确定要继续吗？';
                if (!confirm(confirmMessage)) {
                    $(this).prop('checked', false);
                }
            }
        });
        
        // 其他模块设置相关的交互
        $(document).on('click', '.w2p-module-settings-toggle', function(e){
            // 旧版本兼容性 - 已被标签页替代
            // console.log('Legacy toggle found - use tabs instead');
        });
        
        // console.log('=== ADMIN MODULES JS END ===');
    });
})(jQuery);
