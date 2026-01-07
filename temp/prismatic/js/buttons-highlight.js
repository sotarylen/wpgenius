/* Prismatic - TinyMCE Buttons for Highlight.js */

(function() {
	
	'use strict';
	
	tinymce.create('tinymce.plugins.PrismaticButtons', {
		
		init : function(ed, url) {
			
			ed.addButton('button_prism', {
				
				title   : 'Add Highlight.js Code',
				icon    : 'code',
				
				onclick : function() {
					
					var code = {
						language : '',
						snippet  : ''
					};
					
					ed.windowManager.open({
						
						title     : 'Add Highlight.js code',
						tooltip   : 'Add Highlight.js code',
						minWidth  : 400,
						minHeight : 300,
						
						body : [
							{
								type     : 'listbox',
								name     : 'language',
								value    : '',
								minWidth : 400,
								value    : code.language,
								
								values : [
									{ text : 'Language..',    value : '' },
									
									{ text : 'Apache',        value : 'apache' },
									{ text : 'AppleScript',   value : 'applescript' },
									{ text : 'Arduino',       value : 'arduino' },
									{ text : 'AVR Assembler', value : 'avrasm' },
									{ text : 'Awk',           value : 'awk' },
									{ text : 'Bash',          value : 'bash' },
									{ text : 'C',             value : 'c' },
									{ text : 'CoffeeScript',  value : 'coffeescript' },
									{ text : 'C++',           value : 'cpp' },
									{ text : 'C#',            value : 'cs' },
									{ text : 'CSS',           value : 'css' },
									{ text : 'D',             value : 'd' },
									{ text : 'Dart',          value : 'dart' },
									{ text : 'Diff',          value : 'diff' },
									{ text : 'Elixir',        value : 'elixir' },
									{ text : 'G-code',        value : 'gcode' },
									{ text : 'GML',           value : 'gml' },
									{ text : 'Go',            value : 'go' },
									{ text : 'GraphQL',       value : 'graphql' },
									{ text : 'Groovy',        value : 'groovy' },
									{ text : 'HTTP',          value : 'http' },
									{ text : 'Ini/TOML',      value : 'ini' },
									{ text : 'Java',          value : 'java' },
									{ text : 'JavaScript',    value : 'javascript' },
									{ text : 'JSON',          value : 'json' },
									{ text : 'Julia',         value : 'julia' },
									{ text : 'Kotlin',        value : 'kotlin' },
									{ text : 'LaTeX',         value : 'tex' },
									{ text : 'Less',          value : 'less' },
									{ text : 'Lua',           value : 'lua' },
									{ text : 'Makefile',      value : 'makefile' },
									{ text : 'Markdown',      value : 'markdown' },
									{ text : 'Matlab',        value : 'matlab' },
									{ text : 'Nginx',         value : 'nginx' },
									{ text : 'Objective-C',   value : 'objectivec' },
									{ text : 'Perl',          value : 'perl' },
									{ text : 'PHP',           value : 'php' },
									{ text : 'PHP Template',  value : 'php-template' },
									{ text : 'Plaintext',     value : 'plaintext' },
									{ text : 'PowerShell',    value : 'powershell' },
									{ text : 'Properties',    value : 'properties' },
									{ text : 'Python',        value : 'python' },
									{ text : 'Python REPL',   value : 'python-repl' },
									{ text : 'R',             value : 'r' },
									{ text : 'Ruby',          value : 'ruby' },
									{ text : 'Rust',          value : 'rust' },
									{ text : 'SAS',           value : 'sas' },
									{ text : 'Scala',         value : 'scala' },
									{ text : 'SCSS',          value : 'scss' },
									{ text : 'Shell Session', value : 'shell' },
									{ text : 'SQL',           value : 'sql' },
									{ text : 'Swift',         value : 'swift' },
									{ text : 'TypeScript',    value : 'typescript' },
									{ text : 'VB.Net',        value : 'vbnet' },
									{ text : 'Verilog',       value : 'verilog' },
									{ text : 'VHDL',          value : 'vhdl' },
									{ text : 'Vim Script',    value : 'vim' },
									{ text : 'XML/HTML',      value : 'xml' },
									{ text : 'YAML',          value : 'yaml' },
								],
								
								onselect : function() {
									code.language = this.value();
								},
							},
							{
								type        : 'textbox',
								name        : 'snippet',
								placeholder : 'Add Code Here',
								value       : '',
								minWidth    : 400,
								minHeight   : 300,
								multiline   : true,
								value       : code.snippet,
								
								oninput : function() {
									code.snippet = this.value();
								}
							}
						],
						
						onsubmit : function() {
							ed.insertContent('<pre><code class="language-'+ code.language +'">'+ tinymce.DOM.encode(code.snippet) + '</code></pre>');
						}
						
					});
					
				}
				
			});
			
		},
		
		createControl : function(n, cm) {
			return null;
		},
		
	});
	
	tinymce.PluginManager.add('prismatic_buttons', tinymce.plugins.PrismaticButtons);
	
})();