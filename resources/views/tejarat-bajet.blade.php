<form action="{{ $callbackUrl }}" method="post">
    @csrf
    <input type="number" name="otp">
    <button type="submit">submit</button>
</form>
