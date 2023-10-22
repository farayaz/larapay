<html>

<body>
    <script>
        var form = document.createElement("form");
        form.setAttribute("method", "POST");
        form.setAttribute("action", "{{ $action }}");
        form.setAttribute("target", "_self");
        var fields = @json($fields);
        for (var key in fields) {
            var hiddenField = document.createElement("input");
            hiddenField.setAttribute("name", key);
            hiddenField.setAttribute("value", fields[key]);
            form.appendChild(hiddenField);
        }

        form.appendChild(hiddenField);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    </script>
    redirecting
</body>

</html>