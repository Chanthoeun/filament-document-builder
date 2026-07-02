function registerCustomShapes() {
    if (typeof tinymce !== 'undefined') {
        tinymce.PluginManager.add('custom_shapes', function (editor, url) {
            editor.ui.registry.addMenuButton('custom_templates', {
                text: 'Templates',
                icon: 'template',
                fetch: function (callback) {
                    var templates = editor.getParam('templates', []);
                    var items = [];
                    
                    if (templates.length === 0) {
                        items.push({
                            type: 'menuitem',
                            text: 'No templates available',
                            disabled: true,
                            onAction: function () {}
                        });
                    } else {
                        templates.forEach(function(tpl) {
                            items.push({
                                type: 'menuitem',
                                text: tpl.title,
                                onAction: function () {
                                    editor.insertContent(tpl.content);
                                }
                            });
                        });
                    }
                    callback(items);
                }
            });

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
