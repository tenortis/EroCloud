CKEDITOR.editorConfig = function( config ) {
	config.language = "de";
	config.height = 300;
	customConfig: "myconfig.js";
	
	config.toolbar = [
		{ name: 'clipboard', items: ['Undo', 'Redo' ] },
		{ name: 'editing', items: [ 'Scayt' ] },
		{ name: 'styles', items: ['Format' ] },
		{ name: 'basicstyles', items: [ 'Bold', 'Italic', 'Underline']},
		{ name: 'paragraph', items: [ 'NumberedList', 'BulletedList', '-', 'Outdent', 'Indent'] },
	];     
	
	config.removePlugins = 'elementspath';

};