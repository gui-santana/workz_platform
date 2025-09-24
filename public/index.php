<!DOCTYPE HTML>
<html id="html" class="no-js" lang="pt-br">
	<head>
		<meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Workz!</title>
        <script src="https://cdn.tailwindcss.com"></script>						
		<script type='text/javascript' src="/js/sweetalert.min.js"></script>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>
		<link rel="stylesheet" href="/css/main.css">				
		<link rel="stylesheet" href="/css/footerParallax.css" />
		<link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" integrity="sha384-DyZ88mC6Up2uqS4h/KRgHuoeGwBcD4Ng9SiP4dIRy0EXTlnuz47vAwmeGwVChigm" crossorigin="anonymous"/>
		
		<!-- InteractiveJS -->
		<script type='text/javascript' src="/js/interactive.js"></script>
		<script type='text/javascript' src="/js/autosize.js"></script>
		<link href="css/interactive.css" rel="stylesheet"/>
		<script src="https://www.youtube.com/iframe_api"></script>								
	</head>
	<body class="w-full text-gray-700 bg-gray-100">		
		<div id="loading" class="w-full bg-gray-100 "><div class="la-ball-scale-pulse"><div class="w-shadow"></div></div></div>		
		<div id="desktop" class="w-full fixed top-0 left-0 z-50 grid desktop-area pointer-events-none h-screen" style="pointer-events: none;"></div>
		<div id="main-wrapper" class="w-full h-screen overflow-y-auto overflow-x-hidden snap-y"></div>
		<div id="sidebar-wrapper" class="fixed p-0 m-0 top-0 right-0 z-3 w-0 h-full bg-gray-100 overflow-y-auto transition-all ease-in-out duration-500"></div>
		<script src="https://unpkg.com/imask"></script>
		<script type="module" src="/js/main.js"></script> 		
	</body>
</html>