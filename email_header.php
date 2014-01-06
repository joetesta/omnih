<html>
 <head>
  <link rel="stylesheet" href="src/MarketplaceWebServiceOrders/Samples/jquery-ui.css" />
  <script src="http://code.jquery.com/jquery-1.9.1.js"></script>
  <script src="http://code.jquery.com/ui/1.10.3/jquery-ui.js"></script>
  <link rel="stylesheet" href="src/MarketplaceWebServiceOrders/Samples/jquery_datepicker_style.css" />
  <script>
  $(function() {
    $( "#from" ).datepicker({
      defaultDate: "-1d",
      changeMonth: true,
      numberOfMonths: 1,
      onClose: function( selectedDate ) {
        $( "#to" ).datepicker( "option", "minDate", selectedDate );
        $( "#from" ).datepicker( "option", "dateFormat", "yy-mm-dd" );
      }
    });
    $( "#to" ).datepicker({
      defaultDate: "-1d",
      changeMonth: true,
      numberOfMonths: 1,
      onClose: function( selectedDate ) {
        $( "#from" ).datepicker( "option", "maxDate", selectedDate );
        $( "#to" ).datepicker( "option", "dateFormat", "yy-mm-dd" );
      }
    });

    $('#choosemonth').change(function(){
       document.getElementById("from").value = '';
       document.getElementById("to").value = '';
    });

  });

  </script>

</head>
<body>
