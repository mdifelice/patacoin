Patacoin
========

A toy cryptocurrency.

Requirements
------------

* It needs PHP and uses the OpenSSL library and the PCNTL functions (which are not enabled in Windows). The system runs without issues in Ubuntu and MacOS.
* If they are not created and you want to use the default data folders, you need to create the folder 'data' and, inside, the folders 'node' and 'wallet' to run it properly.

Node
----

Represents a Patacoin node. It will be in charge of maintaining the blockchain by communicating with other nodes and, also, receiving new blocks from miners and transactions from wallets.

* Usage: ./node
* Arguments:
	* debug: If 'yes', it will enable debug mode that, basically, outputs informations to the standard output. Default value is 'no'.
	* folder: Which folder to use to save data. By default it will look for the folder 'data/node' relative to the script.
	* nodes: List of nodes to connect initally. It can be only an IP, in which case it will use the port '80' or an IP and a port separated by a colon (for instance: '127.0.0.1:8765').
	* port: Which port to use to listen for requests. Default is '8765'.

Miner
-----

Is in charge of the mining. It connects to a Patacoin node, that will send the information of the latest blocks and unconfirmed transactions.

* Usage: ./miner
* Arguments:
	* folder: Which folder to look for the wallet data. By default it will look for the folder 'data/wallet' relative to the script.
	* payload: Optional data to append to the block when it is mined.
	* server: Indicates which node to connect. It can be only an IP, in which case it will use the port '80' or an IP and a port separated by a colon. By default it will use '127.0.0.1:8765'.

Wallet
------

Allows to manage a wallet, send transactions and make queries to the connected node, all this by prompting for commands. When running the first timing on a particular folder, it will attempt to create a new address, password-protected. In subsequent calls, it will ask for the entered password in order to access the created address.

* Usage: ./wallet
* Arguments:
	* folder: Which folder to look for the wallet data. By default it will look for the folder 'data/wallet' relative to the script.
	* server: Indicates which node to connect. It can be only an IP, in which case it will use the port '80' or an IP and a port separated by a colon. By default it will use '127.0.0.1:8765'.
* Commands:
	* get_address: Prints the current address.
	* exit: Exits the wallet.
	* send <amount> <receiver> <message>: Allows to create a transaction.
		* amount: How much to transfer. It must be an integer number (Patacoin only works with integers).
		* receiver: Address identifier (its public key) of the receiver.
		* message: Optional message that will be included in the transaction as payload data.
	* update: Updates the address balance by asking for it to the node.
	* get_block_count: Returns the total number of blocks.
	* get_block <block_id>: Prints information of the block in JSON format, and <block_id> can be the block index or the block hash.
	* get_transaction <transaction_id>: Prints informatoin about a transaction, in JSON format. <transaction_id> must the transaction hash.
