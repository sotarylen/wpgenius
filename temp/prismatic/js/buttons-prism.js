/* Prismatic - TinyMCE Buttons for Prism.js */

(function() {
	
	'use strict';
	
	tinymce.create('tinymce.plugins.PrismaticButtons', {
		
		init : function(ed, url) {
			
			ed.addButton('button_prism', {
				
				title   : 'Add Prism.js code',
				icon    : 'code',
				
				onclick : function() {
					
					var code = {
						language : '',
						snippet  : ''
					};
					
					ed.windowManager.open({
						
						title     : 'Add Prism.js code',
						tooltip   : 'Add Prism.js code',
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
									
									{ text : 'Apache',        value : 'apacheconf' },
									{ text : 'AppleScript',   value : 'applescript' },
									{ text : 'Arduino',       value : 'arduino' },
									{ text : 'AVR Assembly',  value : 'asmatmel' },
									{ text : 'Awk',           value : 'awk' },
									{ text : 'Bash',          value : 'bash' },
									{ text : 'Batch',         value : 'batch' },
									{ text : 'C',             value : 'c' },
									{ text : 'C-like',        value : 'clike' },
									{ text : 'CoffeeScript',  value : 'coffeescript' },
									{ text : 'C++',           value : 'cpp' },
									{ text : 'C#',            value : 'csharp' },
									{ text : 'CSS',           value : 'css' },
									{ text : 'D',             value : 'd' },
									{ text : 'Dart',          value : 'dart' },
									{ text : 'Diff',          value : 'diff' },
									{ text : 'Elixir',        value : 'elixir' },
									{ text : 'G-code',        value : 'gcode' },
									{ text : 'Git',           value : 'git' },
									{ text : 'Go',            value : 'go' },
									{ text : 'GraphQL',       value : 'graphql' },
									{ text : 'Groovy',        value : 'groovy' },
									{ text : 'HCL',           value : 'hcl' },
									{ text : 'HTTP',          value : 'http' },
									{ text : 'Ini',           value : 'ini' },
									{ text : 'Java',          value : 'java' },
									{ text : 'JavaScript',    value : 'javascript' },
									{ text : 'JSON',          value : 'json' },
									{ text : 'JSX',           value : 'jsx' },
									{ text : 'Julia',         value : 'julia' },
									{ text : 'Kotlin',        value : 'kotlin' },
									{ text : 'LaTeX',         value : 'latex' },
									{ text : 'Liquid',        value : 'liquid' },
									{ text : 'Lua',           value : 'lua' },
									{ text : 'Makefile',      value : 'makefile' },
									{ text : 'Markdown',      value : 'markdown' },
									{ text : 'Markup/HTML',   value : 'markup' },
									{ text : 'Matlab',        value : 'matlab' },
									{ text : 'Nginx',         value : 'nginx' },
									{ text : 'Objective-C',   value : 'objectivec' },
									{ text : 'Pascal',        value : 'pascal' },
									{ text : 'Perl',          value : 'perl' },
									{ text : 'PHP',           value : 'php' },
									{ text : 'PowerQuery',    value : 'powerquery' },
									{ text : 'PowerShell',    value : 'powershell' },
									{ text : 'Python',        value : 'python' },
									{ text : 'R',             value : 'r' },
									{ text : 'Ruby',          value : 'ruby' },
									{ text : 'Rust',          value : 'rust' },
									{ text : 'SAS',           value : 'sas' },
									{ text : 'SASS',          value : 'sass' },
									{ text : 'Scala',         value : 'scala' },
									{ text : 'SCSS',          value : 'scss' },
									{ text : 'Shell Session', value : 'shell-session' },
									{ text : 'Solidity',      value : 'solidity' },
									{ text : 'SPARQL',        value : 'sparql' },
									{ text : 'Splunk SPL',    value : 'splunk-spl' },
									{ text : 'SQL',           value : 'sql' },
									{ text : 'Swift',         value : 'swift' },
									{ text : 'TSX',           value : 'tsx' },
									{ text : 'Turtle',        value : 'turtle' },
									{ text : 'Twig',          value : 'twig' },
									{ text : 'TypeScript',    value : 'typescript' },
									{ text : 'Verilog',       value : 'verilog' },
									{ text : 'VHDL',          value : 'vhdl' },
									{ text : 'Vim',           value : 'vim' },
									{ text : 'Visual Basic',  value : 'visual-basic' },
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