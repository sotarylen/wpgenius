/* Prismatic - Highlight.js Block */

var 
	el                = wp.element.createElement,
	Fragment          = wp.element.Fragment,
	registerBlockType = wp.blocks.registerBlockType,
	RichText          = wp.editor.RichText,
	PlainText         = wp.blockEditor.PlainText,
	RawHTML           = wp.editor.RawHTML,
	InspectorControls = wp.blockEditor.InspectorControls,
	SelectControl     = wp.components.SelectControl,
	applyFilters      = wp.hooks.applyFilters,
	__                = wp.i18n.__;

registerBlockType('prismatic/blocks', {

	title    : 'Prismatic',
	icon     : 'editor-code',
	category : 'formatting',
	keywords : [ 
		__('code',      'prismatic'), 
		__('pre',       'prismatic'), 
		__('prism',     'prismatic'), 
		__('highlight', 'prismatic'), 
		__('prismatic', 'prismatic') 
	],
	attributes : {
		content : {
			type     : 'string',
			source   : 'text',
			selector : 'pre code',
		},
		language : {
			type    : 'string',
			default : '',
		},
		backgroundColor : {
			type    : 'string',
			default : '#f7f7f7',
		},
		textColor : {
			type    : 'string',
			default : '#373737',
		},
	},

	edit : function(props) {
		
		var 
			content         = props.attributes.content,
			language        = props.attributes.language,
			backgroundColor = props.attributes.backgroundColor,
			textColor       = props.attributes.textColor,
			className       = props.className,
			languages       = [
				{ label : 'Language..',    value : '' },
				
				{ label : 'Apache',        value : 'apache' },
				{ label : 'AppleScript',   value : 'applescript' },
				{ label : 'Arduino',       value : 'arduino' },
				{ label : 'AVR Assembler', value : 'avrasm' },
				{ label : 'Awk',           value : 'awk' },
				{ label : 'Bash',          value : 'bash' },
				{ label : 'C',             value : 'c' },
				{ label : 'CoffeeScript',  value : 'coffeescript' },
				{ label : 'C++',           value : 'cpp' },
				{ label : 'C#',            value : 'cs' },
				{ label : 'CSS',           value : 'css' },
				{ label : 'D',             value : 'd' },
				{ label : 'Dart',          value : 'dart' },
				{ label : 'Diff',          value : 'diff' },
				{ label : 'Elixir',        value : 'elixir' },
				{ label : 'G-code',        value : 'gcode' },
				{ label : 'GML',           value : 'gml' },
				{ label : 'Go',            value : 'go' },
				{ label : 'GraphQL',       value : 'graphql' },
				{ label : 'Groovy',        value : 'groovy' },
				{ label : 'HTTP',          value : 'http' },
				{ label : 'Ini/TOML',      value : 'ini' },
				{ label : 'Java',          value : 'java' },
				{ label : 'JavaScript',    value : 'javascript' },
				{ label : 'JSON',          value : 'json' },
				{ label : 'Julia',         value : 'julia' },
				{ label : 'Kotlin',        value : 'kotlin' },
				{ label : 'LaTeX',         value : 'tex' },
				{ label : 'Less',          value : 'less' },
				{ label : 'Lua',           value : 'lua' },
				{ label : 'Makefile',      value : 'makefile' },
				{ label : 'Markdown',      value : 'markdown' },
				{ label : 'Matlab',        value : 'matlab' },
				{ label : 'Nginx',         value : 'nginx' },
				{ label : 'Objective-C',   value : 'objectivec' },
				{ label : 'Perl',          value : 'perl' },
				{ label : 'PHP',           value : 'php' },
				{ label : 'PHP Template',  value : 'php-template' },
				{ label : 'Plaintext',     value : 'plaintext' },
				{ label : 'PowerShell',    value : 'powershell' },
				{ label : 'Properties',    value : 'properties' },
				{ label : 'Python',        value : 'python' },
				{ label : 'Python REPL',   value : 'python-repl' },
				{ label : 'R',             value : 'r' },
				{ label : 'Ruby',          value : 'ruby' },
				{ label : 'Rust',          value : 'rust' },
				{ label : 'SAS',           value : 'sas' },
				{ label : 'Scala',         value : 'scala' },
				{ label : 'SCSS',          value : 'scss' },
				{ label : 'Shell Session', value : 'shell' },
				{ label : 'SQL',           value : 'sql' },
				{ label : 'Swift',         value : 'swift' },
				{ label : 'TypeScript',    value : 'typescript' },
				{ label : 'VB.Net',        value : 'vbnet' },
				{ label : 'Verilog',       value : 'verilog' },
				{ label : 'VHDL',          value : 'vhdl' },
				{ label : 'Vim Script',    value : 'vim' },
				{ label : 'XML/HTML',      value : 'xml' },
				{ label : 'YAML',          value : 'yaml' },
			];
			
		function onChangeContent(newValue) {
			props.setAttributes({ content: newValue });
		}
		
		function onChangelanguage(newValue) {
			props.setAttributes({ language: newValue });
		}
		
		return (
			el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						SelectControl,
						{
							label    : __('Select Language for Highlight.js', 'prismatic'),
							value    : language,
							onChange : onChangelanguage,
							options  : applyFilters('prismaticHighlightMenu', languages),
						}
					)
				),
				el(
					PlainText,
					{
						tagName     : 'pre',
						key         : 'editable',
						placeholder : __('Add code..', 'prismatic'),
						className   : className,
						onChange    : onChangeContent,
						style       : { backgroundColor : backgroundColor, color : textColor },
						value       : content,
					}
				)
			)
		);
	},
	
	save : function(props) {
		
		var 
			content  = props.attributes.content,
			language = props.attributes.language;
		
		return el('pre', null, el('code', { className: 'language-'+ language }, content));
		
	},
});