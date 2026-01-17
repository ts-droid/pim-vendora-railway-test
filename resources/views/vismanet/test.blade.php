<form method="POST" action="{{ route('visma.test.send') }}">
    @csrf

    <div>
        <label>Method:</label>
        <select name="method" required>
            <option value="GET">GET</option>
            <option value="POST">POST</option>
            <option value="PUT">PUT</option>
            <option value="DELETE">DELETE</option>
        </select>
    </div>

    <br>

    <div>
        <label>Endpoint:</label>
        <input type="text" name="endpoint" style="width: 500px" placeholder="/v1/purchaseorder" required>
    </div>

    <br>

    <div>
        <label>JSON:</label><br>
        <textarea name="body" style="width: 565px;height: 250px;"></textarea>
    </div>

    <br>

    <button type="submit">SEND</button>
</form>
