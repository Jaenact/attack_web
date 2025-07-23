===========================================
🧩 Hex Only - CTF Challenge
===========================================

[🎯 Challenge Description]

You’ve found a server with a strange constraint—it only accepts
hex-encoded Python commands inside an exec() wrapper.
Even worse, certain keywords are strictly filtered out.

Your goal:
=> Retrieve the content of "flag.txt" located in the same directory.

Only payloads like this are allowed:
    exec("\x70\x79\x74\x68\x6f\x6e")

-------------------------------------------
[🔐 Constraints]

- Payload format must match:
  /^exec\((\"|\')\\\\x[0-9a-fA-F]{2}(\\\\x[0-9a-fA-F]{2})*(\"|\')\)$/

- Runs using: python3 -c <your_payload>

- Blocked (case-insensitive) keywords:
  [ open, flag, print, read, import, __, system, file, cat ]

- Output is printed back to the user via the browser

-------------------------------------------
[🚀 How to Interact]

Send a GET request with a query like:
    /?img=exec("<your_hex_payload>")

Or use the form provided in the web interface.

-------------------------------------------
[💡 Hints]

- open() is blocked. Are there alternatives?
- Try indirect ways of reading the file.
- You cannot use "__", but can you access built-ins another way?
- Every payload must be pure hex within an exec() call.

-------------------------------------------
[✅ Example (Safe Test)]

Payload:
    exec("\x70\x72\x69\x6e\x74\x28\x22\x48\x65\x6c\x6c\x6f\x21\x22\x29")

Output:
    Hello!

-------------------------------------------
[🧪 Environment Info]

- PHP 7.x backend
- Python 3 used for payload execution
- flag.txt is in the same directory as the script

-------------------------------------------
[📝 Author]

Challenge by Jaenact (https://github.com/Jaenact)
