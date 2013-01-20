package de.jexp.zmq;

import net.asdfa.msgpack.MsgPack;
import org.zeromq.ZMQ;

import static org.neo4j.helpers.collection.MapUtil.map;

/*
MAVEN_OPTS="-Djava.library.path=/usr/local/lib" mvn exec:java -Dexec.mainClass=de.jexp.zmq.CypherClient -Dexec.arg="create n={name:'foo'}"
 */

public class CypherClient {

    private static final int ROUNDS = 1;

    public static void main(String[] args) {
        ZMQ.Context context = ZMQ.context(1);
        ZMQ.Socket socket = context.socket(ZMQ.REQ);

        System.out.println("Connecting to cypher server...");
        socket.connect("tcp://localhost:5555");
        long time=System.currentTimeMillis();
        long bytes=0;
        String query = args.length>0 ? args[0] : "start n=node(0) return 1";
        System.out.println("query "+query);
        for (int round = 0; round < ROUNDS; round++) {
            // String query = "start n=node(0) match p=n-[r:KNOWS]->m return p,n,r,m,nodes(p) as nodes, rels(p) as rels,length(p) as length";
            byte[] request = MsgPack.pack(map("query",query,"stats",false,"params",map("name","foo")));
            // System.out.println("Sending request " + round + "...");
            socket.send(request, 0);

            boolean more;
            try {
                do {
                    byte[] reply = socket.recv(0);
                    more = socket.hasReceiveMore();
                    bytes+=reply.length;
                    // System.out.println(" length " + reply.length + " more " + more);
                    if (!more) System.out.println("Received reply " + round + ": [" + MsgPack.unpack(reply, MsgPack.UNPACK_RAW_AS_STRING) + "]");
                } while (more);
            } catch (Exception e) {
                System.err.println("Error unpacking ");
                e.printStackTrace();
            }
        }
        System.out.println(ROUNDS+" queries took "+(System.currentTimeMillis()-time)+" ms for "+bytes+" bytes.");
    }
}