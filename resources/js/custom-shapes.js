function registerCustomShapes() {
    console.log("Custom shapes JS is loaded and running!");
    if (typeof tinymce !== 'undefined') {
        tinymce.PluginManager.add('custom_shapes', function (editor, url) {
            editor.ui.registry.addMenuButton('insert_variable', {
                text: 'Insert Variable',
                icon: 'sourcecode',
                fetch: function (callback) {
                    var vars = editor.getParam('document_variables', []);
                    var items = [];
                    
                    if (vars.length === 0) {
                        items.push({
                            type: 'menuitem',
                            text: 'No model selected',
                            disabled: true,
                            onAction: function () {}
                        });
                    } else {
                        vars.forEach(function(v) {
                            items.push({
                                type: 'menuitem',
                                text: '{{ ' + v + ' }}',
                                onAction: function () {
                                    editor.insertContent('{{ ' + v + ' }}');
                                }
                            });
                        });
                    }
                    callback(items);
                }
            });
        });
    } else {
        setTimeout(registerCustomShapes, 100);
    }
}
registerCustomShapes();
